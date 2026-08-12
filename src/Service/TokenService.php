<?php

namespace App\Service;

use App\Entity\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

/**
 * Ports the token helpers from the top of controllers/UserController.js:
 */
class TokenService
{
    private const ACCESS_TOKEN_TTL_SECONDS = 15 * 60;
    private const REFRESH_TOKEN_TTL_SECONDS = 7 * 24 * 60 * 60;

    public function __construct(
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly string $refreshTokenSecret, // bound from %env(REFRESH_TOKEN_SECRET)%
    ) {
    }

    public function signAccessToken(User $user): string
    {
        return $this->jwtManager->createFromPayload($user, [
            'id' => $user->getId(),
            'roles' => $user->getRoles(),
            'exp' => time() + self::ACCESS_TOKEN_TTL_SECONDS,
        ]);
    }

    public function signRefreshToken(User $user): string
    {
        $payload = [
            'id' => $user->getId(),
            'iat' => time(),
            'exp' => time() + self::REFRESH_TOKEN_TTL_SECONDS,
        ];

        return JWT::encode($payload, $this->refreshTokenSecret, 'HS256');
    }

    public function getAccessTokenTtlSeconds(): int
    {
        return self::ACCESS_TOKEN_TTL_SECONDS;
    }

    public function getRefreshTokenTtlSeconds(): int
    {
        return self::REFRESH_TOKEN_TTL_SECONDS;
    }

    /**
     * @return array{id: int} Decoded refresh token payload.
     *
     * @throws \Firebase\JWT\ExpiredException|\UnexpectedValueException On invalid/expired token.
     */
    public function decodeRefreshToken(string $token): array
    {
        $decoded = JWT::decode($token, new Key($this->refreshTokenSecret, 'HS256'));

        return (array) $decoded;
    }
}
