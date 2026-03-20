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
use OpenApi\Attributes as OA;

/**
 * API Authentication Controller.
 * Provides JWT-based authentication for API clients.
 */
#[Route('/api/v1')]
#[OA\Tag(name: 'Authentication', description: 'JWT authentication endpoints')]
class AuthApiController extends AbstractController
{
    public function __construct(
        private JwtService $jwtService
    ) {
    }

    #[Route('/auth/login', name: 'api_auth_login', methods: ['POST'])]
    #[OA\Post(
        summary: 'Authenticate and receive a JWT token',
        description: 'Login with email and password to receive a JWT token for API authentication.'
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['email', 'password'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
                new OA\Property(property: 'password', type: 'string', format: 'password', example: 'yourpassword')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Login successful',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'token', type: 'string', example: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...'),
                new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
                new OA\Property(property: 'expires_in', type: 'integer', example: 3600),
                new OA\Property(property: 'expires_at', type: 'string', format: 'date-time'),
                new OA\Property(property: 'user', type: 'object')
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Invalid JSON or missing credentials')]
    #[OA\Response(response: 401, description: 'Invalid credentials')]
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

    #[Route('/auth/me', name: 'api_auth_me', methods: ['GET'])]
    #[OA\Get(
        summary: 'Get current authenticated user',
        description: 'Returns information about the currently authenticated user.',
        security: [['Bearer' => []]]
    )]
    #[OA\Response(
        response: 200,
        description: 'User information',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'user', type: 'object', properties: [
                    new OA\Property(property: 'id', type: 'integer'),
                    new OA\Property(property: 'email', type: 'string'),
                    new OA\Property(property: 'username', type: 'string'),
                    new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string')),
                    new OA\Property(property: 'createdAt', type: 'string', format: 'date-time')
                ])
            ]
        )
    )]
    #[OA\Response(response: 401, description: 'Authentication required')]
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

    #[Route('/auth/refresh', name: 'api_auth_refresh', methods: ['POST'])]
    #[OA\Post(
        summary: 'Refresh JWT token',
        description: 'Get a new JWT token with extended expiration. Requires a valid current token.',
        security: [['Bearer' => []]]
    )]
    #[OA\Response(
        response: 200,
        description: 'New token generated',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'token', type: 'string'),
                new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
                new OA\Property(property: 'expires_in', type: 'integer', example: 3600),
                new OA\Property(property: 'expires_at', type: 'string', format: 'date-time')
            ]
        )
    )]
    #[OA\Response(response: 401, description: 'Authentication required')]
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
