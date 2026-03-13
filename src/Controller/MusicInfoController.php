<?php

namespace App\Controller;

use App\Repository\AlbumRepository;
use App\Service\DiscogsService;
use App\Service\MusicBrainzService;
use App\Service\RecordStoreLocatorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controller for music information features integrated into the web UI.
 * Provides album lookup, purchase options, and record store locator.
 */
class MusicInfoController extends AbstractController
{
    /**
     * Display purchase options for an album.
     */
    #[Route('/album/{slug}/buy', name: 'album_buy', methods: ['GET'])]
    public function buyAlbum(string $slug, AlbumRepository $albumRepository): Response
    {
        $album = $albumRepository->findOneBy(['slug' => $slug]);
        
        if (!$album) {
            throw $this->createNotFoundException('Album not found');
        }
        
        $discogsService = new DiscogsService();
        $purchaseInfo = $discogsService->findAlbumWithPurchaseOptions(
            $album->getArtist(),
            $album->getTitle()
        );
        
        return $this->render('music_info/buy.html.twig', [
            'album' => $album,
            'purchaseInfo' => $purchaseInfo,
        ]);
    }

    /**
     * Display the record store locator page.
     */
    #[Route('/stores', name: 'record_stores', methods: ['GET'])]
    public function recordStores(Request $request): Response
    {
        $location = $request->query->get('location');
        $stores = null;
        $locationInfo = null;
        
        if ($location) {
            $service = new RecordStoreLocatorService();
            $results = $service->findStoresByLocation($location, 15000);
            
            if (!isset($results['error'])) {
                $stores = $results['stores'];
                $locationInfo = $results['location'];
            }
        }
        
        return $this->render('music_info/stores.html.twig', [
            'location' => $location,
            'locationInfo' => $locationInfo,
            'stores' => $stores,
        ]);
    }

    /**
     * Auto-populate album form fields using MusicBrainz.
     */
    #[Route('/album/lookup', name: 'album_lookup', methods: ['GET'])]
    public function albumLookup(Request $request): Response
    {
        $artist = $request->query->get('artist');
        $title = $request->query->get('title');
        
        if (!$artist) {
            return $this->json(['error' => 'Artist is required']);
        }
        
        $service = new MusicBrainzService();
        
        if ($title) {
            $results = $service->searchAlbum($artist, $title, 5);
        } else {
            $results = $service->searchByArtist($artist, 10);
        }
        
        return $this->json($results);
    }

    /**
     * Get album details from MusicBrainz by MBID (for AJAX calls).
     */
    #[Route('/album/details/{mbid}', name: 'album_details_lookup', methods: ['GET'])]
    public function albumDetailsLookup(string $mbid): Response
    {
        $service = new MusicBrainzService();
        $details = $service->getAlbumDetails($mbid);
        
        return $this->json($details);
    }
}
