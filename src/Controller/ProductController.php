<?php

namespace App\Controller;

use App\Service\MessageGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

class ProductController extends AbstractController
{
    #[Route('/products/new')]
    public function new(MessageGenerator $messageGenerator)
    {
        $message = $messageGenerator->getHappyMessage();
        var_dump($message);
        $this->addFlash('Success', $message);

        // Redirect to another page (where the message will appear)
        return $this->render('hello.html.twig', ['message' => $message] );
    }


}

