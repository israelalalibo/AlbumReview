<?php

namespace App\Controller\Api;

use App\Entity\Album;
use App\Form\AlbumApiType;
use App\Repository\AlbumRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use OpenApi\Attributes as OA;

/**
 * RESTful API Controller for Album resources.
 * Provides CRUD operations for albums with JSON request/response.
 */
#[Route('/api/v1')]
#[OA\Tag(name: 'Albums', description: 'Album CRUD operations')]
class AlbumApiController extends AbstractController
{
    #[Route('/albums', name: 'api_albums_list', methods: ['GET'])]
    #[OA\Get(summary: 'List all albums', description: 'Retrieve a paginated list of albums with optional filtering.')]
    #[OA\Parameter(name: 'genre', in: 'query', description: 'Filter by genre', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'limit', in: 'query', description: 'Max results (default 50, max 100)', required: false, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'offset', in: 'query', description: 'Pagination offset', required: false, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'List of albums')]
    public function list(Request $request, AlbumRepository $repository): JsonResponse
    {
        $criteria = [];
        $orderBy = ['createdAt' => 'DESC'];
        
        // Filter by genre if provided
        if ($genre = $request->query->get('genre')) {
            $criteria['genre'] = $genre;
        }
        
        $limit = min((int) $request->query->get('limit', 50), 100);
        $offset = (int) $request->query->get('offset', 0);
        
        $albums = $repository->findBy($criteria, $orderBy, $limit, $offset);
        $total = $repository->count($criteria);
        
        $data = [
            'albums' => array_map(fn($album) => $this->serializeAlbum($album), $albums),
            'meta' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
            ],
        ];
        
        return $this->createCachedJsonResponse($data, $request, Response::HTTP_OK, 60);
    }

    /**
     * GET /api/v1/albums/{id} - Get a single album by ID
     */
    #[Route('/albums/{id}', name: 'api_albums_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, Request $request, AlbumRepository $repository): JsonResponse
    {
        $album = $repository->find($id);
        
        if (!$album) {
            return new JsonResponse(
                ['error' => 'Album not found', 'code' => 'ALBUM_NOT_FOUND'],
                Response::HTTP_NOT_FOUND
            );
        }
        
        $data = $this->serializeAlbum($album, true);
        return $this->createCachedJsonResponse($data, $request, Response::HTTP_OK, 120);
    }

    /**
     * POST /api/v1/albums - Create a new album
     * Requires authentication.
     * 
     * This endpoint supports idempotency: if an album with the same title and artist
     * already exists, the existing album is returned with 200 OK instead of creating a duplicate.
     * 
     * Request body (JSON):
     * {
     *   "title": "Album Title",
     *   "artist": "Artist Name",
     *   "genre": "Rock",
     *   "releaseYear": 2024,
     *   "trackList": "Track 1\nTrack 2\nTrack 3"
     * }
     */
    #[Route('/albums', name: 'api_albums_create', methods: ['POST'])]
    public function create(Request $request, AlbumRepository $repository, EntityManagerInterface $em): JsonResponse
    {
        // Check authentication
        if (!$this->getUser()) {
            return new JsonResponse(
                ['error' => 'Authentication required', 'code' => 'UNAUTHORIZED'],
                Response::HTTP_UNAUTHORIZED
            );
        }
        
        // Parse JSON body
        $content = $request->getContent();
        $data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new JsonResponse(
                ['error' => 'Invalid JSON', 'detail' => json_last_error_msg()],
                Response::HTTP_BAD_REQUEST
            );
        }
        
        // Idempotency check: Check if album with same title and artist already exists
        $title = $data['title'] ?? null;
        $artist = $data['artist'] ?? null;
        
        if ($title && $artist) {
            $existingAlbum = $repository->findOneBy(['title' => $title, 'artist' => $artist]);
            if ($existingAlbum) {
                $location = $this->generateUrl(
                    'api_albums_show',
                    ['id' => $existingAlbum->getId()],
                    UrlGeneratorInterface::ABSOLUTE_URL
                );
                
                return new JsonResponse(
                    [
                        'message' => 'Album already exists',
                        'code' => 'ALBUM_EXISTS',
                        'album' => $this->serializeAlbum($existingAlbum),
                    ],
                    Response::HTTP_OK,
                    ['Location' => $location]
                );
            }
        }
        
        // Validate using form
        $album = new Album();
        $form = $this->createForm(AlbumApiType::class, $album);
        $form->submit($data);
        
        if (!$form->isValid()) {
            $errors = $this->getFormErrors($form);
            return new JsonResponse(
                ['error' => 'Validation failed', 'messages' => $errors],
                Response::HTTP_BAD_REQUEST
            );
        }
        
        // Set creator and persist
        $album->setCreatedBy($this->getUser());
        $em->persist($album);
        $em->flush();
        
        // Return 201 Created with Location header
        $location = $this->generateUrl(
            'api_albums_show',
            ['id' => $album->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
        
        return new JsonResponse(
            $this->serializeAlbum($album),
            Response::HTTP_CREATED,
            ['Location' => $location]
        );
    }

    /**
     * PUT /api/v1/albums/{id} - Update an existing album
     * Requires authentication and ownership or admin role.
     */
    #[Route('/albums/{id}', name: 'api_albums_update', requirements: ['id' => '\d+'], methods: ['PUT'])]
    public function update(int $id, Request $request, AlbumRepository $repository, EntityManagerInterface $em): JsonResponse
    {
        // Check authentication
        if (!$this->getUser()) {
            return new JsonResponse(
                ['error' => 'Authentication required', 'code' => 'UNAUTHORIZED'],
                Response::HTTP_UNAUTHORIZED
            );
        }
        
        $album = $repository->find($id);
        
        if (!$album) {
            return new JsonResponse(
                ['error' => 'Album not found', 'code' => 'ALBUM_NOT_FOUND'],
                Response::HTTP_NOT_FOUND
            );
        }
        
        // Check ownership or admin
        if ($album->getCreatedBy() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(
                ['error' => 'Access denied', 'code' => 'FORBIDDEN'],
                Response::HTTP_FORBIDDEN
            );
        }
        
        // Parse JSON body
        $content = $request->getContent();
        $data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new JsonResponse(
                ['error' => 'Invalid JSON', 'detail' => json_last_error_msg()],
                Response::HTTP_BAD_REQUEST
            );
        }
        
        // Validate using form (clearMissing: false allows partial updates)
        $form = $this->createForm(AlbumApiType::class, $album);
        $form->submit($data, false);
        
        if (!$form->isValid()) {
            $errors = $this->getFormErrors($form);
            return new JsonResponse(
                ['error' => 'Validation failed', 'messages' => $errors],
                Response::HTTP_BAD_REQUEST
            );
        }
        
        $album->setUpdatedAt(new \DateTimeImmutable());
        $em->flush();
        
        return new JsonResponse(
            $this->serializeAlbum($album),
            Response::HTTP_OK
        );
    }

    /**
     * DELETE /api/v1/albums/{id} - Delete an album
     * Requires authentication and ownership or admin role.
     */
    #[Route('/albums/{id}', name: 'api_albums_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $id, AlbumRepository $repository, EntityManagerInterface $em): JsonResponse
    {
        // Check authentication
        if (!$this->getUser()) {
            return new JsonResponse(
                ['error' => 'Authentication required', 'code' => 'UNAUTHORIZED'],
                Response::HTTP_UNAUTHORIZED
            );
        }
        
        $album = $repository->find($id);
        
        if (!$album) {
            return new JsonResponse(
                ['error' => 'Album not found', 'code' => 'ALBUM_NOT_FOUND'],
                Response::HTTP_NOT_FOUND
            );
        }
        
        // Check ownership or admin
        if ($album->getCreatedBy() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(
                ['error' => 'Access denied', 'code' => 'FORBIDDEN'],
                Response::HTTP_FORBIDDEN
            );
        }
        
        $em->remove($album);
        $em->flush();
        
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Serialize an Album entity to array for JSON response.
     * Excludes sensitive information and includes computed fields.
     */
    private function serializeAlbum(Album $album, bool $includeReviews = false): array
    {
        $data = [
            'id' => $album->getId(),
            'title' => $album->getTitle(),
            'artist' => $album->getArtist(),
            'genre' => $album->getGenre(),
            'releaseYear' => $album->getReleaseYear(),
            'coverImage' => $album->getCoverImage(),
            'trackList' => $album->getTrackListAsArray(),
            'slug' => $album->getSlug(),
            'averageRating' => $album->getAverageRating(),
            'reviewCount' => $album->getReviews()->count(),
            'createdAt' => $album->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'updatedAt' => $album->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
            'createdBy' => [
                'id' => $album->getCreatedBy()?->getId(),
                'username' => $album->getCreatedBy()?->getUsername(),
            ],
            '_links' => [
                'self' => $this->generateUrl('api_albums_show', ['id' => $album->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
                'reviews' => $this->generateUrl('api_reviews_list', ['albumId' => $album->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
            ],
        ];
        
        if ($includeReviews) {
            $data['reviews'] = array_map(fn($review) => [
                'id' => $review->getId(),
                'title' => $review->getTitle(),
                'rating' => $review->getRating(),
                'content' => $review->getContent(),
                'createdAt' => $review->getCreatedAt()?->format(\DateTimeInterface::ATOM),
                'user' => [
                    'id' => $review->getUser()?->getId(),
                    'username' => $review->getUser()?->getUsername(),
                ],
            ], $album->getReviews()->toArray());
        }
        
        return $data;
    }

    /**
     * Extract validation errors from form.
     */
    private function getFormErrors($form): array
    {
        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $field = $error->getOrigin()?->getName() ?? 'general';
            $errors[$field][] = $error->getMessage();
        }
        return $errors;
    }

    /**
     * Build cache-aware JSON response for GET endpoints.
     * Adds Cache-Control and ETag headers, and supports 304 Not Modified.
     */
    private function createCachedJsonResponse(array $data, Request $request, int $status = Response::HTTP_OK, int $maxAge = 60): JsonResponse
    {
        $response = new JsonResponse($data, $status);
        $response->setPublic();
        $response->setMaxAge($maxAge);
        $response->setSharedMaxAge($maxAge);
        $response->setEtag(hash('sha256', json_encode($data) ?: ''));

        if ($response->isNotModified($request)) {
            return $response;
        }

        return $response;
    }
}
