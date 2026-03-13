<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\JwtService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * API Authentication Controller.
 * Provides JWT-based authentication for API clients.
 */
#[Route('/api/v1')]
class AuthApiController extends AbstractController
{
    public function __construct(
        private JwtService $jwtService
    ) {
    }

    /**
     * POST /api/v1/auth/login - Authenticate and receive a JWT token
     * 
     * Request body (JSON):
     * {
     *   "email": "user@example.com",
     *   "password": "yourpassword"
     * }
     * 
     * Response:
     * {
     *   "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
     *   "token_type": "Bearer",
     *   "expires_in": 3600,
     *   "user": { ... }
     * }
     */
    #[Route('/auth/login', name: 'api_auth_login', methods: ['POST'])]
    public function login(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        $content = $request->getContent();
        $data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new JsonResponse(
                ['error' => 'Invalid JSON', 'detail' => json_last_error_msg()],
                Response::HTTP_BAD_REQUEST
            );
        }
        
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;
        
        if (!$email || !$password) {
            return new JsonResponse(
                ['error' => 'Email and password are required'],
                Response::HTTP_BAD_REQUEST
            );
        }
        
        $user = $userRepository->findOneBy(['email' => $email]);
        
        if (!$user || !$passwordHasher->isPasswordValid($user, $password)) {
            return new JsonResponse(
                ['error' => 'Invalid credentials', 'code' => 'INVALID_CREDENTIALS'],
                Response::HTTP_UNAUTHORIZED
            );
        }
        
        $token = $this->jwtService->createToken($user);
        $expiresIn = $this->jwtService->getTokenTtl();
        
        return new JsonResponse([
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $expiresIn,
            'expires_at' => date(\DateTimeInterface::ATOM, time() + $expiresIn),
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'username' => $user->getUsername(),
                'roles' => $user->getRoles(),
            ],
        ], Response::HTTP_OK);
    }

    /**
     * GET /api/v1/auth/me - Get current authenticated user info
     * Requires JWT Bearer token in Authorization header.
     * 
     * This endpoint now uses Symfony's security system via the API firewall,
     * so $this->getUser() returns the authenticated user from the JWT.
     */
    #[Route('/auth/me', name: 'api_auth_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        
        if (!$user) {
            return new JsonResponse(
                ['error' => 'Authentication required', 'code' => 'UNAUTHORIZED'],
                Response::HTTP_UNAUTHORIZED
            );
        }
        
        return new JsonResponse([
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'username' => $user->getUsername(),
                'roles' => $user->getRoles(),
                'createdAt' => $user->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            ],
        ], Response::HTTP_OK);
    }

    /**
     * POST /api/v1/auth/refresh - Refresh an existing JWT token
     * Requires a valid (non-expired) JWT Bearer token.
     * Returns a new token with extended expiration.
     */
    #[Route('/auth/refresh', name: 'api_auth_refresh', methods: ['POST'])]
    public function refresh(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        
        if (!$user) {
            return new JsonResponse(
                ['error' => 'Authentication required', 'code' => 'UNAUTHORIZED'],
                Response::HTTP_UNAUTHORIZED
            );
        }
        
        $token = $this->jwtService->createToken($user);
        $expiresIn = $this->jwtService->getTokenTtl();
        
        return new JsonResponse([
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $expiresIn,
            'expires_at' => date(\DateTimeInterface::ATOM, time() + $expiresIn),
        ], Response::HTTP_OK);
    }
}
