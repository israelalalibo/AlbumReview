<?php

namespace App\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Service for interacting with the MusicBrainz API.
 * MusicBrainz is a community-maintained open-source music database.
 * 
 * API Documentation: https://musicbrainz.org/doc/MusicBrainz_API
 */
class MusicBrainzService
{
    private Client $client;
    private const BASE_URI = 'https://musicbrainz.org/ws/2/';
    private const USER_AGENT = 'AlbumReviews/1.0 (university-project)';

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => self::BASE_URI,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => self::USER_AGENT,
            ],
            'timeout' => 15,
            'verify' => false,
        ]);
    }

    /**
     * Search for albums (releases) by artist and title.
     * 
     * @param string $artist Artist name
     * @param string $title Album title
     * @param int $limit Maximum results
     * @return array Array of release results
     */
    public function searchAlbum(string $artist, string $title, int $limit = 10): array
    {
        try {
            $query = sprintf('artist:"%s" AND release:"%s"', $artist, $title);
            
            $response = $this->client->get('release', [
                'query' => [
                    'query' => $query,
                    'fmt' => 'json',
                    'limit' => $limit,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            return $this->formatReleaseResults($data['releases'] ?? []);
            
        } catch (GuzzleException $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Search for releases by artist name only.
     */
    public function searchByArtist(string $artist, int $limit = 20): array
    {
        try {
            $response = $this->client->get('release', [
                'query' => [
                    'query' => sprintf('artist:"%s"', $artist),
                    'fmt' => 'json',
                    'limit' => $limit,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            return $this->formatReleaseResults($data['releases'] ?? []);
            
        } catch (GuzzleException $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get detailed album information by MusicBrainz ID (MBID).
     * Includes recordings (tracks), artist credits, and label info.
     */
    public function getAlbumDetails(string $mbid): ?array
    {
        try {
            $response = $this->client->get("release/{$mbid}", [
                'query' => [
                    'inc' => 'recordings+artist-credits+labels+release-groups',
                    'fmt' => 'json',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            if (!$data) {
                return null;
            }

            return [
                'mbid' => $data['id'] ?? null,
                'title' => $data['title'] ?? null,
                'artist' => $this->extractArtistName($data['artist-credit'] ?? []),
                'releaseDate' => $data['date'] ?? null,
                'country' => $data['country'] ?? null,
                'barcode' => $data['barcode'] ?? null,
                'status' => $data['status'] ?? null,
                'label' => $this->extractLabelName($data['label-info'] ?? []),
                'trackList' => $this->extractTrackList($data['media'] ?? []),
                'trackCount' => $this->countTracks($data['media'] ?? []),
            ];
            
        } catch (GuzzleException $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get artist information by MBID.
     */
    public function getArtistInfo(string $mbid): ?array
    {
        try {
            $response = $this->client->get("artist/{$mbid}", [
                'query' => [
                    'inc' => 'releases+release-groups',
                    'fmt' => 'json',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            return [
                'mbid' => $data['id'] ?? null,
                'name' => $data['name'] ?? null,
                'sortName' => $data['sort-name'] ?? null,
                'type' => $data['type'] ?? null,
                'country' => $data['country'] ?? null,
                'disambiguation' => $data['disambiguation'] ?? null,
                'beginArea' => $data['begin-area']['name'] ?? null,
                'lifeSpan' => [
                    'begin' => $data['life-span']['begin'] ?? null,
                    'end' => $data['life-span']['end'] ?? null,
                    'ended' => $data['life-span']['ended'] ?? false,
                ],
            ];
            
        } catch (GuzzleException $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Format release search results into a cleaner structure.
     */
    private function formatReleaseResults(array $releases): array
    {
        return array_map(function ($release) {
            return [
                'mbid' => $release['id'] ?? null,
                'title' => $release['title'] ?? null,
                'artist' => $this->extractArtistName($release['artist-credit'] ?? []),
                'releaseDate' => $release['date'] ?? null,
                'country' => $release['country'] ?? null,
                'trackCount' => $release['track-count'] ?? null,
                'score' => $release['score'] ?? null,
            ];
        }, $releases);
    }

    /**
     * Extract primary artist name from artist credits.
     */
    private function extractArtistName(array $artistCredits): ?string
    {
        if (empty($artistCredits)) {
            return null;
        }
        
        $names = array_map(function ($credit) {
            return $credit['artist']['name'] ?? $credit['name'] ?? '';
        }, $artistCredits);
        
        return implode(', ', array_filter($names));
    }

    /**
     * Extract label name from label info.
     */
    private function extractLabelName(array $labelInfo): ?string
    {
        if (empty($labelInfo)) {
            return null;
        }
        
        return $labelInfo[0]['label']['name'] ?? null;
    }

    /**
     * Extract track list from media array.
     */
    private function extractTrackList(array $media): array
    {
        $tracks = [];
        
        foreach ($media as $medium) {
            foreach ($medium['tracks'] ?? [] as $track) {
                $tracks[] = [
                    'position' => $track['position'] ?? null,
                    'title' => $track['title'] ?? $track['recording']['title'] ?? null,
                    'length' => $track['length'] ?? null,
                    'lengthFormatted' => $this->formatDuration($track['length'] ?? 0),
                ];
            }
        }
        
        return $tracks;
    }

    /**
     * Count total tracks across all media.
     */
    private function countTracks(array $media): int
    {
        $count = 0;
        foreach ($media as $medium) {
            $count += count($medium['tracks'] ?? []);
        }
        return $count;
    }

    /**
     * Format milliseconds to MM:SS.
     */
    private function formatDuration(int $milliseconds): string
    {
        if ($milliseconds <= 0) {
            return '0:00';
        }
        
        $seconds = (int) ($milliseconds / 1000);
        $minutes = (int) ($seconds / 60);
        $seconds = $seconds % 60;
        
        return sprintf('%d:%02d', $minutes, $seconds);
    }
}
