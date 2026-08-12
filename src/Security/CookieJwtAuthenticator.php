<?php

namespace App\Security;

use App\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

class CookieJwtAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly UserRepository $userRepository
    ) {
    }

    public function supports(Request $request): ?bool
    {
        $path = $request->getPathInfo();

        /*
         * Refresh endpoints do not require access token.
         */
        if (str_contains($path, '/refresh-token')) {
            return false;
        }

        /*
         * USER API
         */
        if (str_starts_with($path, '/api/user')) {
            return $request->cookies->has('userAccessToken');
        }

        /*
         * DRIVER API
         */
        if (str_starts_with($path, '/api/driver')) {
            return $request->cookies->has('driverAccessToken');
        }

        /*
         * ADMIN API
         */
        if (str_starts_with($path, '/api/admin')) {
            return $request->cookies->has('adminAccessToken');
        }

        return false;
    }

    public function authenticate(Request $request): Passport
    {
        $path = $request->getPathInfo();

        /*
         * Determine which access-token cookie belongs
         * to the current API.
         */
        if (str_starts_with($path, '/api/admin')) {
            $token = $request->cookies->get('adminAccessToken');

        } elseif (str_starts_with($path, '/api/driver')) {
            $token = $request->cookies->get('driverAccessToken');

        } else {
            $token = $request->cookies->get('userAccessToken');
        }

        if (!$token) {
            throw new AuthenticationException(
                'Missing access token'
            );
        }

        try {
            $payload = $this->jwtManager->parse($token);
        } catch (\Throwable $e) {
            throw new AuthenticationException(
                'Invalid JWT token'
            );
        }

        if (!isset($payload['id'])) {
            throw new AuthenticationException(
                'Invalid token payload'
            );
        }

        return new SelfValidatingPassport(
            new UserBadge(
                (string) $payload['id'],
                function ($userIdentifier) {
                    $user = $this->userRepository->find(
                        $userIdentifier
                    );

                    if (!$user) {
                        throw new AuthenticationException(
                            'User not found'
                        );
                    }

                    return $user;
                }
            )
        );
    }

    public function onAuthenticationSuccess(
        Request $request,
        \Symfony\Component\Security\Core\Authentication\Token\TokenInterface $token,
        string $firewallName
    ): ?Response {
        return null;
    }

    public function onAuthenticationFailure(
        Request $request,
        AuthenticationException $exception
    ): ?Response {
        return new JsonResponse([
            'success' => false,
            'message' => $exception->getMessageKey(),
            'debug' => $exception->getMessage(),
        ], 401);
    }
}