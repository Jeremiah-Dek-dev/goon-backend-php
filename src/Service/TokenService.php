<?php

namespace App\Service;

use App\Entity\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

/**
 * Ports the token helpers from the top of controllers/UserController.js:
 *
 *   const signAccessToken = (user) => jwt.sign({id, roles}, JWT_SECRET, {expiresIn: "15m"})
 *   const signRefreshToken = (user) => jwt.sign({id}, REFRESH_TOKEN_SECRET, {expiresIn: "7d"})
 *
 * Kept as two DIFFERENT signing mechanisms on purpose, matching the Node
 * app's use of two separate secrets - access tokens go through Lexik
 * (RSA keypair, config/packages/lexik_jwt_authentication.yaml), refresh
 * tokens are signed directly with firebase/php-jwt against a plain HMAC
 * secret (REFRESH_TOKEN_SECRET), same as Node's jsonwebtoken call.
 *
 * NOTE: this bypasses Lexik's automatic json_login success handler
 * entirely, since tokens here are set as cookies with custom claims, not
 * returned as a Bearer token in the response body. See PHASE4_NOTES.md
 * for the follow-up needed on the `api`/`admin` firewalls in security.yaml.
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
