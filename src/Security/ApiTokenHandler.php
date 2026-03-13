<?php

namespace App\Security;

use App\Repository\UserRepository;
use App\Service\JwtService;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/**
 * Handles API Bearer token authentication using JWT.
 * Validates JWT tokens and returns the associated user.
 */
class ApiTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(
        private UserRepository $userRepository,
        private JwtService $jwtService
    ) {
    }

    public function getUserBadgeFrom(string $accessToken): UserBadge
    {
        $payload = $this->jwtService->decodeToken($accessToken);
        
        if ($payload === null) {
            throw new BadCredentialsException('Invalid or expired JWT token');
        }
        
        $userId = $payload['sub'] ?? null;
        if (!$userId) {
            throw new BadCredentialsException('Invalid token: missing subject');
        }
        
        $user = $this->userRepository->find($userId);
        if (!$user) {
            throw new BadCredentialsException('User not found');
        }
        
        return new UserBadge($user->getUserIdentifier());
    }
}
