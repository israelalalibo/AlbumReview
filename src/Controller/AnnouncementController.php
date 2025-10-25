<?php

namespace App\Controller;

use App\Entity\Announcement;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AnnouncementController extends AbstractController
{
    #[Route('/new_announcement/{message}', name: 'new_announcement')]
    public function new(string $message, EntityManagerInterface $entityManager): Response
    {
        // 1. Create a new Announcement object
        $announcement = new Announcement();

        // 2. Set the message (from the route parameter)
        $announcement->setMessage($message);

        // 3. Set the current timestamp (server datetime)
        $announcement->setTimestamp(new \DateTime());

        // 4. Persist (save) the object
        $entityManager->persist($announcement);

        // 5. Actually write it to the database
        $entityManager->flush();

        // 6. Return a response
        return new Response("✅ New announcement added: '{$message}' at " . $announcement->getTimestamp()->format('Y-m-d H:i:s'));
    }
}
