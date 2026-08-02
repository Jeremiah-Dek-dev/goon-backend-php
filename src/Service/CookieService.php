<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

class CookieService
{

    public function setAuthCookies(
        Response $response,
        string $accessToken,
        string $refreshToken
    ): void {

        $response->headers->setCookie(
            $this->createCookie(
                'userAccessToken',
                $accessToken,
                15 * 60
            )
        );


        $response->headers->setCookie(
            $this->createCookie(
                'userRefreshToken',
                $refreshToken,
                7 * 24 * 60 * 60
            )
        );
    }


    public function getRefreshToken(Request $request): ?string
    {
        return $request->cookies->get(
            'userRefreshToken'
        );
    }

    
    public function clearAuthCookies(
        Response $response
    ): void {


        $response->headers->clearCookie(
            'userAccessToken',
            '/',
        );


        $response->headers->clearCookie(
            'userRefreshToken',
            '/',
        );
    }




    private function createCookie(
        string $name,
        string $value,
        int $ttl
    ): Cookie {


        return Cookie::create(
            $name,
            $value,
            time() + $ttl,
            '/',
            null,
            true,   // secure HTTPS only
            true,   // HTTP only
            false,
            Cookie::SAMESITE_LAX
        );
    }

}