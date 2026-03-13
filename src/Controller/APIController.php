<?php

namespace App\Controller;

use App\Entity\Announcement;
use App\Form\AnnouncementAPIType;
use App\Repository\AnnouncementRepository;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class APIController extends AbstractFOSRestController
{
    /**
     * List all announcements (REST: GET /api/v1/announcements).
     * Rest annotations are enabled via "use FOS\RestBundle\Controller\Annotations as Rest";
     * we use Symfony's Route so the route is registered (FOS Rest 3 does not auto-load Rest routes).
     */
    #[Route('/api/v1/announcements', name: 'api_announcements_list', methods: ['GET'])]
    public function announcementsList(AnnouncementRepository $announcementRepository): JsonResponse
    {
        $announcements = $announcementRepository->findBy([], ['timestamp' => 'DESC']);
        $data = array_map(fn ($a) => [
            'id' => $a->getId(),
            'message' => $a->getMessage(),
            'timestamp' => $a->getTimestamp()?->format(\DateTimeInterface::ATOM),
        ], $announcements);

        return new JsonResponse($data);
    }

    #[Route('/api/v1/announcements/{id}', name: 'api_announcement_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function getAnnouncement(int $id, AnnouncementRepository $announcementRepository): JsonResponse
    {
        $announcement = $announcementRepository->find($id);

        if (!$announcement) {
            return new JsonResponse(['error' => 'Announcement not found'], 404);
        }

        $data = [
            'id' => $announcement->getId(),
            'message' => $announcement->getMessage(),
            'timestamp' => $announcement->getTimestamp()?->format(\DateTimeInterface::ATOM),
        ];

        return new JsonResponse($data);
    }

    /**
     * Create a new announcement (REST: POST /api/v1/announcements).
     * Expects JSON body: {"message": "Your announcement text"}
     * Returns 201 Created with Location header, or 400 on invalid/parse error.
     */
    #[Route('/api/v1/announcements', name: 'api_announcements_create', methods: ['POST'])]
    public function createAnnouncement(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        // Step 1: Parse POST body as JSON (we expect JSON)
        $content = $request->getContent();
        $data = json_decode($content, true);

        if (json_last_error() !== \JSON_ERROR_NONE) {
            return new JsonResponse(
                ['error' => 'Invalid JSON', 'detail' => json_last_error_msg()],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Step 2: Validate using the API form (AnnouncementAPIType, no CSRF)
        $announcement = new Announcement();
        $form = $this->createForm(AnnouncementAPIType::class, $announcement);
        $form->submit($data);

        if (!$form->isValid()) {
            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $errors[] = $error->getMessage();
            }
            return new JsonResponse(
                ['error' => 'Validation failed', 'messages' => $errors],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Step 3: Same as web interface — set timestamp and persist
        $announcement->setTimestamp(new \DateTime());
        $entityManager->persist($announcement);
        $entityManager->flush();

        // Step 4: 201 Created + Location header to GET the created resource
        $location = $this->generateUrl(
            'api_announcement_show',
            ['id' => $announcement->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $responseData = [
            'id' => $announcement->getId(),
            'message' => $announcement->getMessage(),
            'timestamp' => $announcement->getTimestamp()?->format(\DateTimeInterface::ATOM),
        ];

        return new JsonResponse($responseData, Response::HTTP_CREATED, [
            'Location' => $location,
        ]);
    }
}
