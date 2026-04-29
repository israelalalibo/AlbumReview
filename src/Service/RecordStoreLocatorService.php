<?php

namespace App\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Service for finding nearby record/music stores using OpenStreetMap.
 * 
 * Uses:
 * - Nominatim API for geocoding (address to coordinates)
 * - Overpass API for finding music shops
 * 
 * This is a creative feature that helps users find physical stores
 * where they can purchase vinyl records and CDs.
 */
class RecordStoreLocatorService
{
    private Client $nominatimClient;
    private Client $overpassClient;
    
    private const NOMINATIM_BASE = 'https://nominatim.openstreetmap.org/';
    private const OVERPASS_BASE = 'https://overpass-api.de/api/';
    private const USER_AGENT = 'AlbumReviews/1.0 (university-project)';

    public function __construct()
    {
        $this->nominatimClient = new Client([
            'base_uri' => self::NOMINATIM_BASE,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => self::USER_AGENT,
            ],
            'timeout' => 15,
            'verify' => false,
        ]);
        
        $this->overpassClient = new Client([
            'base_uri' => self::OVERPASS_BASE,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => self::USER_AGENT,
            ],
            'timeout' => 30,
            'verify' => false,
        ]);
    }

    /**
     * Geocode an address to coordinates.
     * 
     * @param string $address Address, city, or postcode
     * @return array|null ['lat' => float, 'lon' => float, 'displayName' => string]
     */
    public function geocodeAddress(string $address): ?array
    {
        try {
            $response = $this->nominatimClient->get('search', [
                'query' => [
                    'q' => $address,
                    'format' => 'json',
                    'limit' => 1,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            if (empty($data)) {
                return null;
            }
            
            return [
                'lat' => (float) $data[0]['lat'],
                'lon' => (float) $data[0]['lon'],
                'displayName' => $data[0]['display_name'] ?? null,
            ];
            
        } catch (GuzzleException $e) {
            return null;
        }
    }

    /**
     * Find record stores near a location.
     * 
     * @param float $lat Latitude
     * @param float $lon Longitude
     * @param int $radius Radius in meters (default 10km)
     * @return array List of nearby stores
     */
    public function findNearbyRecordStores(float $lat, float $lon, int $radius = 10000): array
    {
        try {
            // Overpass QL query to find music shops and record stores
            $query = sprintf(
                '[out:json][timeout:25];
                (
                  node["shop"="music"](around:%d,%f,%f);
                  way["shop"="music"](around:%d,%f,%f); 
                  node["shop"="records"](around:%d,%f,%f);
                  way["shop"="records"](around:%d,%f,%f);
                  node["shop"="hifi"](around:%d,%f,%f);
                  way["shop"="hifi"](around:%d,%f,%f);
                );
                out body;
                >;
                out skel qt;',
                $radius, $lat, $lon,
                $radius, $lat, $lon,
                $radius, $lat, $lon,
                $radius, $lat, $lon,
                $radius, $lat, $lon,
                $radius, $lat, $lon
            );

            $response = $this->overpassClient->post('interpreter', [
                'form_params' => [
                    'data' => $query,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            return $this->formatStoreResults($data['elements'] ?? [], $lat, $lon);
            
        } catch (GuzzleException $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Find record stores by city/location name.
     * Combines geocoding with store search.
     */
    public function findStoresByLocation(string $location, int $radius): array
    {
        $coords = $this->geocodeAddress($location); // Get coordinates for the location
        
        if (!$coords) {
            return ['error' => 'Could not find location: ' . $location];
        }
        
        $stores = $this->findNearbyRecordStores($coords['lat'], $coords['lon'], $radius);
        
        return [
            'location' => $coords,
            'stores' => $stores,
            'count' => is_array($stores) && !isset($stores['error']) ? count($stores) : 0,
        ];
    }

    /**
     * Format store results with distance calculation.
     */
    private function formatStoreResults(array $elements, float $userLat, float $userLon): array
    {
        $stores = [];
        
        foreach ($elements as $element) {
            // Only process nodes with relevant tags
            if ($element['type'] !== 'node' || empty($element['tags'])) {
                continue;
            }
            
            $tags = $element['tags'];
            
            // Calculate distance
            $storeLat = $element['lat'] ?? 0;
            $storeLon = $element['lon'] ?? 0;
            $distance = $this->calculateDistance($userLat, $userLon, $storeLat, $storeLon);
            
            $stores[] = [
                'id' => $element['id'] ?? null,
                'name' => $tags['name'] ?? 'Unknown Store',
                'type' => $tags['shop'] ?? 'music',
                'address' => $this->formatAddress($tags),
                'coordinates' => [
                    'lat' => $storeLat,
                    'lon' => $storeLon,
                ],
                'distance' => [
                    'meters' => round($distance),
                    'km' => round($distance / 1000, 2),
                    'miles' => round($distance / 1609.344, 2),
                ],
                'contact' => [
                    'phone' => $tags['phone'] ?? $tags['contact:phone'] ?? null,
                    'website' => $tags['website'] ?? $tags['contact:website'] ?? null,
                    'email' => $tags['email'] ?? $tags['contact:email'] ?? null,
                ],
                'openingHours' => $tags['opening_hours'] ?? null,
                'wheelchair' => $tags['wheelchair'] ?? null,
                'osmUrl' => "https://www.openstreetmap.org/node/{$element['id']}",
                'mapsUrl' => "https://www.google.com/maps?q={$storeLat},{$storeLon}",
            ];
        }
        
        // Sort by distance
        usort($stores, fn($a, $b) => $a['distance']['meters'] <=> $b['distance']['meters']);
        
        return $stores;
    }

    /**
     * Format address from OSM tags.
     */
    private function formatAddress(array $tags): ?string
    {
        $parts = array_filter([
            $tags['addr:housenumber'] ?? null,
            $tags['addr:street'] ?? null,
            $tags['addr:city'] ?? null,
            $tags['addr:postcode'] ?? null,
        ]);
        
        return !empty($parts) ? implode(', ', $parts) : null;
    }

    /**
     * Calculate distance between two points using Haversine formula.
     * 
     * @param float $lat1 Latitude of point 1
     * @param float $lon1 Longitude of point 1
     * @param float $lat2 Latitude of point 2
     * @param float $lon2 Longitude of point 2
     * @return float Distance in meters
     */
    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000; // meters
        
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLon = deg2rad($lon2 - $lon1);
        
        $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
             cos($lat1Rad) * cos($lat2Rad) *
             sin($deltaLon / 2) * sin($deltaLon / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        return $earthRadius * $c;
    }
}
