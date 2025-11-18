<?php

namespace App\Controller;

use App\Entity\Album;
use App\Form\AlbumType;
use App\Repository\AlbumRepository;
use App\Service\FileUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/albums')]
class AlbumController extends AbstractController
{
    #[Route('/', name: 'album_index', methods: ['GET'])]
    public function index(
        Request $request,
        AlbumRepository $albumRepository,
        PaginatorInterface $paginator
    ): Response {
        $query = $request->query->get('q', '');
        $genre = $request->query->get('genre', '');
        $year = $request->query->get('year', '');

        // Convert year to integer only if it's numeric
        $yearInt = null;
        if ($year !== '' && is_numeric($year)) {
            $yearInt = (int) $year;
        }

        $albums = $albumRepository->findBySearchCriteria(
            $query ?: null,
            $genre ?: null,
            $yearInt
        );


        $pagination = $paginator->paginate(
            $albums,
            $request->query->getInt('page', 1),
            12
        );

        $genres = $albumRepository->findAllGenres();

        return $this->render('album/index.html.twig', [
            'albums' => $pagination,
            'genres' => $genres,
            'current_query' => $query,
            'current_genre' => $genre,
            'current_year' => $year,
        ]);
    }

    #[Route('/new', name: 'album_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        FileUploadService $fileUploadService
    ): Response {
        $album = new Album();
        $form = $this->createForm(AlbumType::class, $album);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $coverImageFile = $form->get('coverImageFile')->getData();
            if ($coverImageFile) {
                $filename = $fileUploadService->upload($coverImageFile, 'albums');
                $album->setCoverImage($filename);
            }

            $album->setCreatedBy($this->getUser());
            $entityManager->persist($album);
            $entityManager->flush();

            $this->addFlash('success', 'Album created successfully!');
            return $this->redirectToRoute('album_show', ['slug' => $album->getSlug()]);
        }

        return $this->render('album/new.html.twig', [
            'album' => $album,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{slug}', name: 'album_show', methods: ['GET'])]
    public function show(Album $album): Response
    {
        return $this->render('album/show.html.twig', [
            'album' => $album,
        ]);
    }

    #[Route('/{slug}/edit', name: 'album_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function edit(
        Request $request,
        Album $album,
        EntityManagerInterface $entityManager,
        FileUploadService $fileUploadService
    ): Response {
        // Only creator or admin/moderator can edit
        if ($album->getCreatedBy() !== $this->getUser() &&
            !$this->isGranted('ROLE_MODERATOR')) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(AlbumType::class, $album);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $coverImageFile = $form->get('coverImageFile')->getData();
            if ($coverImageFile) {
                // Delete old image if exists
                if ($album->getCoverImage()) {
                    $fileUploadService->delete($album->getCoverImage(), 'albums');
                }
                $filename = $fileUploadService->upload($coverImageFile, 'albums');
                $album->setCoverImage($filename);
            }

            $album->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            $this->addFlash('success', 'Album updated successfully!');
            return $this->redirectToRoute('album_show', ['slug' => $album->getSlug()]);
        }

        return $this->render('album/edit.html.twig', [
            'album' => $album,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'album_delete', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function delete(
        Request $request,
        Album $album,
        EntityManagerInterface $entityManager,
        FileUploadService $fileUploadService
    ): Response {
        // Only creator or admin can delete
        if ($album->getCreatedBy() !== $this->getUser() &&
            !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete'.$album->getId(), $request->request->get('_token'))) {
            if ($album->getCoverImage()) {
                $fileUploadService->delete($album->getCoverImage(), 'albums');
            }
            $entityManager->remove($album);
            $entityManager->flush();
            $this->addFlash('success', 'Album deleted successfully!');
        }

        return $this->redirectToRoute('album_index');
    }
}
