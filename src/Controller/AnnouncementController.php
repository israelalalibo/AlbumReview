<?php

namespace App\Controller;

use App\Entity\Announcement;
use App\Form\AnnouncementType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AnnouncementController extends AbstractController
{
    #[Route('/announcementForm', name: 'announcement_form_new', methods: ['GET', 'POST'])]
    public function announcement2(Request $request, EntityManagerInterface $entityManager): Response
    {
        $announcement = new Announcement(); //create a new Announcement object to be populated
        var_dump("Start I was here");
        $form = $this->createForm(AnnouncementType::class, $announcement);//link to actual data from form to Announcement object

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // $form->getData() holds the submitted values
            // but, the original `$task` variable has also been updated
            $task = $form->getData();


            //saving the task to the database
            $announcement->setTimestamp(new \DateTime());

            //persist to database
            $entityManager->persist($announcement);
            $entityManager->flush();


            $this->addFlash('success', 'Announcement saved successfully!');
            return $this->redirectToRoute('announcement_form_new');
        }


        return $this->render('announcement.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/new_announcement', name: 'new_announcement_form', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        // Handle form submission (only when POST)
        if ($request->isMethod('POST')) {
            $message = $request->request->get('message');

            if (trim($message) !== '') {
                $announcement = new Announcement();
                $announcement->setMessage($message);
                $announcement->setTimestamp(new \DateTime());

                $entityManager->persist($announcement);
                $entityManager->flush();

                // Optional: Flash message for confirmation
                $this->addFlash('success', 'Announcement saved successfully!');
                return $this->redirectToRoute('new_announcement_form');
            } else {
                $this->addFlash('error', 'Message cannot be empty.');
            }
        }

        // Render the form template
        return $this->render('new.html.twig');
        //return $this->render('hello.html.twig');
    }
}
