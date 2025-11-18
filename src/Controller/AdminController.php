<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    #[Route('/users', name: 'admin_users')]
    public function users(UserRepository $userRepository): Response
    {
        $users = $userRepository->findAll();

        return $this->render('admin/users.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/users/{id}/role', name: 'admin_user_role', methods: ['POST'])]
    public function updateUserRole(
        Request $request,
        User $user,
        EntityManagerInterface $entityManager
    ): Response {
        $role = $request->request->get('role');

        $allowedRoles = ['ROLE_USER', 'ROLE_MODERATOR', 'ROLE_ADMIN'];
        if (!in_array($role, $allowedRoles)) {
            $this->addFlash('error', 'Invalid role');
            return $this->redirectToRoute('admin_users');
        }

        $user->setRoles([$role]);
        $entityManager->flush();

        $this->addFlash('success', 'User role updated successfully');
        return $this->redirectToRoute('admin_users');
    }
}
