<?php

namespace App\Service;

use App\Entity\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Service for generating and validating JWT tokens.
 * Uses HS256 algorithm with APP_SECRET as the signing key.
 */
class JwtService
{
    private const ALGORITHM = 'HS256';
    private const TOKEN_TTL = 3600; // 1 hour

    public function __construct(
        private string $secretKey,
        private string $issuer = 'albumreviews-api'
    ) {
    }

    /**
     * Generate a JWT token for a user.
     */
    public function createToken(User $user): string
    {
        $issuedAt = time();
        $expiresAt = $issuedAt + self::TOKEN_TTL;

        $payload = [
            'iss' => $this->issuer,           // Issuer
            'iat' => $issuedAt,               // Issued at
            'exp' => $expiresAt,              // Expiration
            'nbf' => $issuedAt,               // Not before
            'sub' => (string) $user->getId(), // Subject (user ID)
            'email' => $user->getEmail(),
            'username' => $user->getUsername(),
            'roles' => $user->getRoles(),
        ];

        return JWT::encode($payload, $this->secretKey, self::ALGORITHM);
    }

    /**
     * Decode and validate a JWT token.
     * Returns the payload if valid, null if invalid.
     */
    public function decodeToken(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secretKey, self::ALGORITHM));
            return (array) $decoded;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get the token TTL in seconds.
     */
    public function getTokenTtl(): int
    {
        return self::TOKEN_TTL;
    }
}
