<?php

namespace App\Controller;

use App\Entity\Album;
use App\Entity\Review;
use App\Form\ReviewType;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/albums/{slug}/reviews')]
#[IsGranted('ROLE_USER')]
class ReviewController extends AbstractController
{
    #[Route('/new', name: 'review_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        Album $album,
        EntityManagerInterface $entityManager,
        ReviewRepository $reviewRepository
    ): Response {
        // Check if user already reviewed this album
        $existingReview = $reviewRepository->findUserReviewForAlbum($this->getUser(), $album);
        if ($existingReview) {
            $this->addFlash('warning', 'You have already reviewed this album. Edit your existing review instead.');
            return $this->redirectToRoute('album_show', ['slug' => $album->getSlug()]);
        }

        $review = new Review();
        $form = $this->createForm(ReviewType::class, $review);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $review->setUser($this->getUser());
            $review->setAlbum($album);

            $entityManager->persist($review);
            $entityManager->flush();

            $this->addFlash('success', 'Review added successfully!');
            return $this->redirectToRoute('album_show', ['slug' => $album->getSlug()]);
        }

        return $this->render('review/new.html.twig', [
            'album' => $album,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{reviewId}/edit', name: 'review_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Album $album,
        int $reviewId,
        EntityManagerInterface $entityManager,
        ReviewRepository $reviewRepository
    ): Response {
        $review = $reviewRepository->find($reviewId);

        if (!$review) {
            throw $this->createNotFoundException('Review not found');
        }
        // Only review owner or moderator can edit
        if ($review->getUser() !== $this->getUser() &&
            !$this->isGranted('ROLE_MODERATOR')) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(ReviewType::class, $review);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $review->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            $this->addFlash('success', 'Review updated successfully!');
            return $this->redirectToRoute('album_show', ['slug' => $album->getSlug()]);
        }

        return $this->render('review/edit.html.twig', [
            'album' => $album,
            'review' => $review,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{reviewId}', name: 'review_delete', methods: ['POST'])]
    #[Entity('album', expr: 'repository.findOneBySlug(slug)')]
    public function delete(
        Request $request,
        Album $album,
        Review $review,
        EntityManagerInterface $entityManager
    ): Response {
        // Only review owner or admin can delete
        if ($review->getUser() !== $this->getUser() &&
            !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        //Need to make sure to avoid cross site Request forgery before deleting
        if ($this->isCsrfTokenValid('delete'.$review->getId(), $request->request->get('_token'))) {
            $entityManager->remove($review);
            $entityManager->flush();
            $this->addFlash('success', 'Review deleted successfully!');
        }

        return $this->redirectToRoute('album_show', ['slug' => $album->getSlug()]);
    }
}
