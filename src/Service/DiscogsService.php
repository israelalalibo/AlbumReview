<?php

namespace App\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Service for interacting with the Discogs API.
 * Discogs is a music database and marketplace for vinyl records and CDs.
 * 
 * Features:
 * - Search for releases
 * - Get marketplace listings (where to buy)
 * - Get price suggestions
 * - Find sellers
 * 
 * API Documentation: https://www.discogs.com/developers
 */
class DiscogsService
{
    private Client $client;
    private const BASE_URI = 'https://api.discogs.com/';
    private const USER_AGENT = 'AlbumReviews/1.0';

    public function __construct(?string $discogsToken = null)
    {
        $headers = [
            'Accept' => 'application/json',
            'User-Agent' => self::USER_AGENT,
        ];
        
        // Add token if available (allows more API calls)
        if ($discogsToken) {
            $headers['Authorization'] = 'Discogs token=' . $discogsToken;
        }

        $this->client = new Client([
            'base_uri' => self::BASE_URI,
            'headers' => $headers,
            'timeout' => 15,
            'verify' => false,
        ]);
    }

    /**
     * Search for releases by artist and album title.
     */
    public function searchRelease(string $artist, string $title): array
    {
        try {
            $response = $this->client->get('database/search', [
                'query' => [
                    'artist' => $artist,
                    'release_title' => $title,
                    'type' => 'release',
                    'per_page' => 10,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            return $this->formatSearchResults($data['results'] ?? []);
            
        } catch (GuzzleException $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get detailed release information including marketplace stats.
     */
    public function getReleaseDetails(int $releaseId): ?array
    {
        try {
            $response = $this->client->get("releases/{$releaseId}");
            $data = json_decode($response->getBody()->getContents(), true);
            
            if (!$data) {
                return null;
            }

            return [
                'id' => $data['id'] ?? null,
                'title' => $data['title'] ?? null,
                'artists' => $this->extractArtists($data['artists'] ?? []),
                'year' => $data['year'] ?? null,
                'genres' => $data['genres'] ?? [],
                'styles' => $data['styles'] ?? [],
                'labels' => $this->extractLabels($data['labels'] ?? []),
                'formats' => $this->extractFormats($data['formats'] ?? []),
                'country' => $data['country'] ?? null,
                'tracklist' => $this->extractTracklist($data['tracklist'] ?? []),
                'images' => $this->extractImages($data['images'] ?? []),
                'community' => [
                    'have' => $data['community']['have'] ?? 0,
                    'want' => $data['community']['want'] ?? 0,
                    'rating' => $data['community']['rating']['average'] ?? 0,
                    'ratingCount' => $data['community']['rating']['count'] ?? 0,
                ],
                'lowestPrice' => $data['lowest_price'] ?? null,
                'numForSale' => $data['num_for_sale'] ?? 0,
                'marketplaceUrl' => "https://www.discogs.com/sell/release/{$data['id']}",
                'discogsUrl' => $data['uri'] ?? null,
            ];
            
        } catch (GuzzleException $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get marketplace listings for a release (where to buy).
     */
    public function getMarketplaceListings(int $releaseId, int $limit = 10): array
    {
        try {
            $response = $this->client->get("marketplace/listings", [
                'query' => [
                    'release_id' => $releaseId,
                    'per_page' => $limit,
                    'sort' => 'price',
                    'sort_order' => 'asc',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            return $this->formatListings($data['listings'] ?? []);
            
        } catch (GuzzleException $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get price statistics for a release.
     */
    public function getPriceStats(int $releaseId): ?array
    {
        try {
            $response = $this->client->get("marketplace/price_suggestions/{$releaseId}");
            $data = json_decode($response->getBody()->getContents(), true);
            
            $stats = [];
            foreach ($data as $condition => $prices) {
                $stats[$condition] = [
                    'value' => $prices['value'] ?? 0,
                    'currency' => $prices['currency'] ?? 'USD',
                ];
            }
            
            return $stats;
            
        } catch (GuzzleException $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Search and get combined info including marketplace data.
     */
    public function findAlbumWithPurchaseOptions(string $artist, string $title): ?array
    {
        $searchResults = $this->searchRelease($artist, $title);
        
        if (empty($searchResults) || isset($searchResults['error'])) {
            return null;
        }
        
        // Get the first (most relevant) result
        $firstResult = $searchResults[0];
        
        if (!isset($firstResult['id'])) {
            return null;
        }
        
        // Get detailed info
        $details = $this->getReleaseDetails($firstResult['id']);
        
        if (!$details || isset($details['error'])) {
            return $firstResult;
        }
        
        return $details;
    }

    private function formatSearchResults(array $results): array
    {
        return array_map(function ($result) {
            return [
                'id' => $result['id'] ?? null,
                'title' => $result['title'] ?? null,
                'year' => $result['year'] ?? null,
                'country' => $result['country'] ?? null,
                'format' => $result['format'] ?? [],
                'label' => $result['label'] ?? [],
                'genre' => $result['genre'] ?? [],
                'style' => $result['style'] ?? [],
                'coverImage' => $result['cover_image'] ?? null,
                'thumb' => $result['thumb'] ?? null,
                'resourceUrl' => $result['resource_url'] ?? null,
            ];
        }, $results);
    }

    private function extractArtists(array $artists): array
    {
        return array_map(function ($artist) {
            return [
                'name' => $artist['name'] ?? null,
                'id' => $artist['id'] ?? null,
            ];
        }, $artists);
    }

    private function extractLabels(array $labels): array
    {
        return array_map(function ($label) {
            return [
                'name' => $label['name'] ?? null,
                'catno' => $label['catno'] ?? null,
            ];
        }, $labels);
    }

    private function extractFormats(array $formats): array
    {
        return array_map(function ($format) {
            return [
                'name' => $format['name'] ?? null,
                'qty' => $format['qty'] ?? null,
                'descriptions' => $format['descriptions'] ?? [],
            ];
        }, $formats);
    }

    private function extractTracklist(array $tracklist): array
    {
        return array_map(function ($track) {
            return [
                'position' => $track['position'] ?? null,
                'title' => $track['title'] ?? null,
                'duration' => $track['duration'] ?? null,
            ];
        }, $tracklist);
    }

    private function extractImages(array $images): array
    {
        return array_map(function ($image) {
            return [
                'type' => $image['type'] ?? null,
                'uri' => $image['uri'] ?? null,
                'uri150' => $image['uri150'] ?? null,
                'width' => $image['width'] ?? null,
                'height' => $image['height'] ?? null,
            ];
        }, array_slice($images, 0, 5)); // Limit to 5 images
    }

    private function formatListings(array $listings): array
    {
        return array_map(function ($listing) {
            return [
                'id' => $listing['id'] ?? null,
                'price' => [
                    'value' => $listing['price']['value'] ?? null,
                    'currency' => $listing['price']['currency'] ?? null,
                ],
                'condition' => $listing['condition'] ?? null,
                'sleeveCondition' => $listing['sleeve_condition'] ?? null,
                'shipsFrom' => $listing['ships_from'] ?? null,
                'seller' => [
                    'username' => $listing['seller']['username'] ?? null,
                    'rating' => $listing['seller']['stats']['rating'] ?? null,
                ],
                'uri' => $listing['uri'] ?? null,
            ];
        }, $listings);
    }
}
