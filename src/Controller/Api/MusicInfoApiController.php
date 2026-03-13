<?php

namespace App\Controller\Api;

use App\Service\DiscogsService;
use App\Service\MusicBrainzService;
use App\Service\RecordStoreLocatorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * API Controller for external music information services.
 * Consumes MusicBrainz, Discogs, and OpenStreetMap APIs.
 */
#[Route('/api/v1/music')]
class MusicInfoApiController extends AbstractController
{
    /**
     * GET /api/v1/music/search - Search for album information
     * 
     * Query parameters:
     * - artist: Artist name (required)
     * - title: Album title (optional)
     * - source: API source - 'musicbrainz' or 'discogs' (default: musicbrainz)
     */
    #[Route('/search', name: 'api_music_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $artist = $request->query->get('artist');
        $title = $request->query->get('title');
        $source = $request->query->get('source', 'musicbrainz');
        
        if (!$artist) {
            return new JsonResponse(
                ['error' => 'Artist parameter is required'],
                Response::HTTP_BAD_REQUEST
            );
        }
        
        if ($source === 'discogs') {
            $service = new DiscogsService();
            $results = $title 
                ? $service->searchRelease($artist, $title)
                : $service->searchRelease($artist, '');
        } else {
            $service = new MusicBrainzService();
            $results = $title
                ? $service->searchAlbum($artist, $title)
                : $service->searchByArtist($artist);
        }
        
        return new JsonResponse([
            'source' => $source,
            'query' => ['artist' => $artist, 'title' => $title],
            'results' => $results,
        ], Response::HTTP_OK);
    }

    /**
     * GET /api/v1/music/album/{mbid} - Get album details from MusicBrainz
     */
    #[Route('/album/{mbid}', name: 'api_music_album_details', methods: ['GET'])]
    public function albumDetails(string $mbid): JsonResponse
    {
        $service = new MusicBrainzService();
        $details = $service->getAlbumDetails($mbid);
        
        if (!$details || isset($details['error'])) {
            return new JsonResponse(
                ['error' => $details['error'] ?? 'Album not found'],
                Response::HTTP_NOT_FOUND
            );
        }
        
        return new JsonResponse($details, Response::HTTP_OK);
    }

    /**
     * GET /api/v1/music/purchase - Find where to buy an album
     * 
     * Query parameters:
     * - artist: Artist name (required)
     * - title: Album title (required)
     * 
     * Returns Discogs marketplace information including prices and sellers.
     */
    #[Route('/purchase', name: 'api_music_purchase', methods: ['GET'])]
    public function findPurchaseOptions(Request $request): JsonResponse
    {
        $artist = $request->query->get('artist');
        $title = $request->query->get('title');
        
        if (!$artist || !$title) {
            return new JsonResponse(
                ['error' => 'Artist and title parameters are required'],
                Response::HTTP_BAD_REQUEST
            );
        }
        
        $service = new DiscogsService();
        $details = $service->findAlbumWithPurchaseOptions($artist, $title);
        
        if (!$details) {
            return new JsonResponse(
                ['error' => 'Album not found on Discogs marketplace'],
                Response::HTTP_NOT_FOUND
            );
        }
        
        return new JsonResponse([
            'album' => [
                'artist' => $artist,
                'title' => $title,
            ],
            'discogs' => $details,
            'purchaseLinks' => [
                'discogs' => $details['marketplaceUrl'] ?? null,
                'amazon' => "https://www.amazon.com/s?k=" . urlencode("{$artist} {$title} vinyl"),
                'ebay' => "https://www.ebay.com/sch/i.html?_nkw=" . urlencode("{$artist} {$title} vinyl"),
            ],
        ], Response::HTTP_OK);
    }

    /**
     * GET /api/v1/music/stores - Find nearby record stores
     * 
     * Query parameters:
     * - location: City name or address (required)
     * - radius: Search radius in meters (default: 10000)
     */
    #[Route('/stores', name: 'api_music_stores', methods: ['GET'])]
    public function findRecordStores(Request $request): JsonResponse
    {
        $location = $request->query->get('location');
        $radius = (int) $request->query->get('radius', 10000);
        
        if (!$location) {
            return new JsonResponse(
                ['error' => 'Location parameter is required'],
                Response::HTTP_BAD_REQUEST
            );
        }
        
        // Limit radius to max 50km
        $radius = min($radius, 50000);
        
        $service = new RecordStoreLocatorService();
        $results = $service->findStoresByLocation($location, $radius);
        
        if (isset($results['error'])) {
            return new JsonResponse(
                ['error' => $results['error']],
                Response::HTTP_NOT_FOUND
            );
        }
        
        return new JsonResponse([
            'query' => [
                'location' => $location,
                'radius' => $radius,
            ],
            'location' => $results['location'],
            'stores' => $results['stores'],
            'count' => $results['count'],
        ], Response::HTTP_OK);
    }

    /**
     * GET /api/v1/music/stores/coordinates - Find stores by coordinates
     * 
     * Query parameters:
     * - lat: Latitude (required)
     * - lon: Longitude (required)
     * - radius: Search radius in meters (default: 10000)
     */
    #[Route('/stores/coordinates', name: 'api_music_stores_coords', methods: ['GET'])]
    public function findRecordStoresByCoords(Request $request): JsonResponse
    {
        $lat = $request->query->get('lat');
        $lon = $request->query->get('lon');
        $radius = (int) $request->query->get('radius', 10000);
        
        if (!$lat || !$lon) {
            return new JsonResponse(
                ['error' => 'lat and lon parameters are required'],
                Response::HTTP_BAD_REQUEST
            );
        }
        
        $service = new RecordStoreLocatorService();
        $stores = $service->findNearbyRecordStores((float) $lat, (float) $lon, min($radius, 50000));
        
        if (isset($stores['error'])) {
            return new JsonResponse(
                ['error' => $stores['error']],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
        
        return new JsonResponse([
            'coordinates' => ['lat' => (float) $lat, 'lon' => (float) $lon],
            'radius' => $radius,
            'stores' => $stores,
            'count' => count($stores),
        ], Response::HTTP_OK);
    }
}
