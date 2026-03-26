<?php

namespace App\Controller\Api;

use App\Entity\Review;
use App\Form\ReviewApiType;
use App\Repository\AlbumRepository;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use OpenApi\Attributes as OA;

/**
 * RESTful API Controller for Review resources.
 * Reviews are sub-resources of Albums, following RESTful best practices.
 */
#[Route('/api/v1')]
#[OA\Tag(name: 'Reviews', description: 'Review operations (sub-resource of Albums)')]
class ReviewApiController extends AbstractController
{
    /**
     * GET /api/v1/albums/{albumId}/reviews - List all reviews for an album
     */
    #[Route('/albums/{albumId}/reviews', name: 'api_reviews_list', requirements: ['albumId' => '\d+'], methods: ['GET'])]
    public function list(int $albumId, Request $request, AlbumRepository $albumRepository): JsonResponse
    {
        $album = $albumRepository->find($albumId);

        if (!$album) {
            return new JsonResponse(
                ['error' => 'Album not found', 'code' => 'ALBUM_NOT_FOUND'],
                Response::HTTP_NOT_FOUND
            );
        }

        $reviews = array_map(
            fn($review) => $this->serializeReview($review),
            $album->getReviews()->toArray()
        );

        $data = [
            'reviews' => $reviews,
            'meta' => [
                'total' => count($reviews),
                'albumId' => $albumId,
                'albumTitle' => $album->getTitle(),
            ],
        ];

        return $this->createCachedJsonResponse($data, $request, Response::HTTP_OK, 60);
    }

    /**
     * GET /api/v1/albums/{albumId}/reviews/{id} - Get a single review
     */
    #[Route('/albums/{albumId}/reviews/{id}', name: 'api_reviews_show', requirements: ['albumId' => '\d+', 'id' => '\d+'], methods: ['GET'])]
    public function show(int $albumId, int $id, Request $request, AlbumRepository $albumRepository, ReviewRepository $reviewRepository): JsonResponse
    {
        $album = $albumRepository->find($albumId);

        if (!$album) {
            return new JsonResponse(
                ['error' => 'Album not found', 'code' => 'ALBUM_NOT_FOUND'],
                Response::HTTP_NOT_FOUND
            );
        }

        $review = $reviewRepository->find($id);

        if (!$review || $review->getAlbum()?->getId() !== $albumId) {
            return new JsonResponse(
                ['error' => 'Review not found', 'code' => 'REVIEW_NOT_FOUND'],
                Response::HTTP_NOT_FOUND
            );
        }

        $data = $this->serializeReview($review, true);
        return $this->createCachedJsonResponse($data, $request, Response::HTTP_OK, 120);
    }

    /**
     * POST /api/v1/albums/{albumId}/reviews - Create a new review
     * Requires authentication.
     * 
     * This endpoint is idempotent: each user can only have one review per album.
     * If a review already exists, the existing review is returned with 200 OK.
     * 
     * Optional header for client-side idempotency:
     *   Idempotency-Key: <unique-key>
     *
     * Request body (JSON):
     * {
     *   "title": "Great Album!",
     *   "content": "This album is amazing because...",
     *   "rating": 9
     * }
     */
    #[Route('/albums/{albumId}/reviews', name: 'api_reviews_create', requirements: ['albumId' => '\d+'], methods: ['POST'])]
    public function create(
        int $albumId, 
        Request $request, 
        AlbumRepository $albumRepository, 
        ReviewRepository $reviewRepository,
        EntityManagerInterface $em
    ): JsonResponse {
        // Check authentication
        if (!$this->getUser()) {
            return new JsonResponse(
                ['error' => 'Authentication required', 'code' => 'UNAUTHORIZED'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        $album = $albumRepository->find($albumId);
        if (!$album) {
            return new JsonResponse(
                ['error' => 'Album not found', 'code' => 'ALBUM_NOT_FOUND'],
                Response::HTTP_NOT_FOUND
            );
        }

        // Idempotency check: User can only have one review per album
        // If review exists, return the existing review (idempotent behavior)
        $existingReview = $reviewRepository->findUserReviewForAlbum($this->getUser(), $album);
        if ($existingReview) {
            $location = $this->generateUrl(
                'api_reviews_show',
                ['albumId' => $albumId, 'id' => $existingReview->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );
            
            return new JsonResponse(
                [
                    'message' => 'Review already exists for this album',
                    'code' => 'REVIEW_EXISTS',
                    'review' => $this->serializeReview($existingReview),
                ],
                Response::HTTP_OK,
                ['Location' => $location]
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

        // Validate using form
        $review = new Review();
        $form = $this->createForm(ReviewApiType::class, $review);
        $form->submit($data);

        if (!$form->isValid()) {
            $errors = $this->getFormErrors($form);
            return new JsonResponse(
                ['error' => 'Validation failed', 'messages' => $errors],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Set relationships
        $review->setAlbum($album);
        $review->setUser($this->getUser());

        $em->persist($review);
        $em->flush();

        // Return 201 Created with Location header
        $location = $this->generateUrl(
            'api_reviews_show',
            ['albumId' => $albumId, 'id' => $review->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        return new JsonResponse(
            $this->serializeReview($review),
            Response::HTTP_CREATED,
            ['Location' => $location]
        );
    }

    /**
     * PUT /api/v1/albums/{albumId}/reviews/{id} - Update a review
     * Requires authentication and ownership or admin role.
     */
    #[Route('/albums/{albumId}/reviews/{id}', name: 'api_reviews_update', requirements: ['albumId' => '\d+', 'id' => '\d+'], methods: ['PUT'])]
    public function update(int $albumId, int $id, Request $request, AlbumRepository $albumRepository, ReviewRepository $reviewRepository, EntityManagerInterface $em): JsonResponse
    {
        // Check authentication
        if (!$this->getUser()) {
            return new JsonResponse(
                ['error' => 'Authentication required', 'code' => 'UNAUTHORIZED'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        $album = $albumRepository->find($albumId);

        if (!$album) {
            return new JsonResponse(
                ['error' => 'Album not found', 'code' => 'ALBUM_NOT_FOUND'],
                Response::HTTP_NOT_FOUND
            );
        }

        $review = $reviewRepository->find($id);

        if (!$review || $review->getAlbum()?->getId() !== $albumId) {
            return new JsonResponse(
                ['error' => 'Review not found', 'code' => 'REVIEW_NOT_FOUND'],
                Response::HTTP_NOT_FOUND
            );
        }

        // Check ownership or admin
        if ($review->getUser() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
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
        $form = $this->createForm(ReviewApiType::class, $review);
        $form->submit($data, false);

        if (!$form->isValid()) {
            $errors = $this->getFormErrors($form);
            return new JsonResponse(
                ['error' => 'Validation failed', 'messages' => $errors],
                Response::HTTP_BAD_REQUEST
            );
        }

        $review->setUpdatedAt(new \DateTimeImmutable());
        $em->flush();

        return new JsonResponse(
            $this->serializeReview($review),
            Response::HTTP_OK
        );
    }

    /**
     * DELETE /api/v1/albums/{albumId}/reviews/{id} - Delete a review
     * Requires authentication and ownership or admin role.
     */
    #[Route('/albums/{albumId}/reviews/{id}', name: 'api_reviews_delete', requirements: ['albumId' => '\d+', 'id' => '\d+'], methods: ['DELETE'])]
    public function delete(int $albumId, int $id, AlbumRepository $albumRepository, ReviewRepository $reviewRepository, EntityManagerInterface $em): JsonResponse
    {
        // Check authentication
        if (!$this->getUser()) {
            return new JsonResponse(
                ['error' => 'Authentication required', 'code' => 'UNAUTHORIZED'],
                Response::HTTP_UNAUTHORIZED
            );
        }

        $album = $albumRepository->find($albumId);

        if (!$album) {
            return new JsonResponse(
                ['error' => 'Album not found', 'code' => 'ALBUM_NOT_FOUND'],
                Response::HTTP_NOT_FOUND
            );
        }

        $review = $reviewRepository->find($id);

        if (!$review || $review->getAlbum()?->getId() !== $albumId) {
            return new JsonResponse(
                ['error' => 'Review not found', 'code' => 'REVIEW_NOT_FOUND'],
                Response::HTTP_NOT_FOUND
            );
        }

        // Check ownership or admin
        if ($review->getUser() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(
                ['error' => 'Access denied', 'code' => 'FORBIDDEN'],
                Response::HTTP_FORBIDDEN
            );
        }

        $em->remove($review);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Serialize a Review entity to array for JSON response.
     */
    private function serializeReview(Review $review, bool $includeAlbum = false): array
    {
        $data = [
            'id' => $review->getId(),
            'title' => $review->getTitle(),
            'content' => $review->getContent(),
            'rating' => $review->getRating(),
            'createdAt' => $review->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'updatedAt' => $review->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
            'user' => [
                'id' => $review->getUser()?->getId(),
                'username' => $review->getUser()?->getUsername(),
            ],
            '_links' => [
                'self' => $this->generateUrl(
                    'api_reviews_show',
                    ['albumId' => $review->getAlbum()?->getId(), 'id' => $review->getId()],
                    UrlGeneratorInterface::ABSOLUTE_URL
                ),
                'album' => $this->generateUrl(
                    'api_albums_show',
                    ['id' => $review->getAlbum()?->getId()],
                    UrlGeneratorInterface::ABSOLUTE_URL
                ),
            ],
        ];

        if ($includeAlbum) {
            $album = $review->getAlbum();
            $data['album'] = [
                'id' => $album?->getId(),
                'title' => $album?->getTitle(),
                'artist' => $album?->getArtist(),
                'slug' => $album?->getSlug(),
            ];
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
