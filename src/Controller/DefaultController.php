<?php

namespace App\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DefaultController extends AbstractController
{

    #[Route('/about', name: 'about')]
    public function about()

    {
        $team = array("Andrei", "Kingsley", "Julie", "Kacper",
            "Rebecca", "Junaid", "Jesse", "Oliver", "Nathan");
        return $this->render('layout.html.twig', [
            'team' => $team,
        ]);

    }

    #[Route('/hello/world', name: 'hello')]
    public function hello(LoggerInterface $logger)
    {
        $logger->info('Look I have just used a logger!');
        return $this->render('hello.html.twig');

    }

    #[Route('/products', name: 'product')]
    public function list(LoggerInterface $logger)
    {
        $logger->info('Look I have just used a logger!');

    }
}
