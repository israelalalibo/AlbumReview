<?php

namespace App\Controller;

use App\Repository\AlbumRepository;
use App\Repository\ReviewRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(
        AlbumRepository $albumRepository,
        ReviewRepository $reviewRepository
    ): Response {
        $topRatedAlbums = $albumRepository->findTopRatedAlbums(6);
        $latestReviews = $reviewRepository->findLatestReviews(5);

        return $this->render('home/index.html.twig', [
            'topRatedAlbums' => $topRatedAlbums,
            'latestReviews' => $latestReviews,
        ]);
    }
}
