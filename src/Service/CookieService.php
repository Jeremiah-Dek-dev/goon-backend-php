<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

class CookieService
{


    private const COOKIE_DOMAIN = null;





    /**
     * Existing USER cookies
     */
    public function setAuthCookies(
        Response $response,
        string $accessToken,
        string $refreshToken
    ): void {


        $response->headers->setCookie(

            new Cookie(

                'userAccessToken',

                $accessToken,

                time() + (15 * 60),

                '/api/user',

                self::COOKIE_DOMAIN,

                true,

                true,
                false,
                Cookie::SAMESITE_NONE

            )

        );





        $response->headers->setCookie(

            new Cookie(

                'userRefreshToken',

                $refreshToken,

                time() + (7 * 24 * 60 * 60),

                '/api/user',

                self::COOKIE_DOMAIN,

                true,

                true,
                false,
                Cookie::SAMESITE_NONE

            )

        );


    }










    /**
     * DRIVER cookies
     *
     * Equivalent Node:
     *
     * setAppCookie(
     *   res,
     *   "driverAccessToken"
     * )
     */
    public function setDriverAuthCookies(

        Response $response,

        string $accessToken,

        string $refreshToken

    ): void {


        $response->headers->setCookie(

            new Cookie(

                'driverAccessToken',

                $accessToken,

                time() + (15 * 60),

                '/api/driver',

                self::COOKIE_DOMAIN,

                true,

                true,

                false,

                Cookie::SAMESITE_NONE

            )

        );






        $response->headers->setCookie(

            new Cookie(

                'driverRefreshToken',

                $refreshToken,

                time() + (7 * 24 * 60 * 60),

                '/api/driver',

                self::COOKIE_DOMAIN,

                true,

                true,

                false,

                Cookie::SAMESITE_NONE

            )

        );


    }










    /**
     * Clear USER authentication
     */
    public function clearAuthCookies(
        Response $response
    ): void {


        $response->headers->clearCookie(

            'userAccessToken',

            '/api/user'

        );


        $response->headers->clearCookie(

            'userRefreshToken',

            '/api/user'

        );


    }










    /**
     * Clear DRIVER authentication
     */
    public function clearDriverAuthCookies(
        Response $response
    ): void {


        $response->headers->clearCookie(

            'driverAccessToken',

            '/api/driver'

        );


        $response->headers->clearCookie(

            'driverRefreshToken',

            '/api/driver'

        );


    }




    #  ADMIN COOKIE SESSION

    public function setAdminAuthCookies(
        Response $response,
        string $adminAccessToken,
        string $adminRefreshToken
    ): void {


        $response->headers->setCookie(

            new Cookie(

                'adminAccessToken',

                $adminAccessToken,

                time() + (15 * 60),

                '/api/admin',

                self::COOKIE_DOMAIN,

                true,

                true,
                false,
                Cookie::SAMESITE_NONE

            )

        );





        $response->headers->setCookie(

            new Cookie(

                'adminRefreshToken',

                $adminRefreshToken,

                time() + (7 * 24 * 60 * 60),

                '/api/admin',

                self::COOKIE_DOMAIN,

                true,

                true,
                false,
                Cookie::SAMESITE_NONE

            )

        );


    }




    # CLEAR ADMIN COOKIE SESSION
        /**
     * Clear ADMIN authentication
     */
    public function clearAdminAuthCookies(
        Response $response
    ): void {


        $response->headers->clearCookie(

            'adminAccessToken',

            '/api/admin'

        );


        $response->headers->clearCookie(

            'adminRefreshToken',

            '/api/admin'

        );


        $response->headers->clearCookie(

            'adminCaptchaToken',

            '/api/admin'

        );


    }




    ## ADMIN CAPTCHA COOKIE SESSION
    public function setAdminCaptchaCookie(
        Response $response,
        string $captchaToken
    ): void {
        
        $response->headers->setCookie(

            new Cookie(

                'adminCaptchaToken',

                $captchaToken,

                time() + (5 * 60),

                '/api/admin',

                self::COOKIE_DOMAIN,

                true,

                true,
                false,
                Cookie::SAMESITE_NONE

            )

        );

    }





        /**
     * ADMIN PRE-2FA VERIFICATION SESSION
     */
public function setVerificationToken(
    Response $response,
    string $token
): void {

    $response->headers->setCookie(
        new Cookie(
            'adminVerificationToken',
            $token,
            time() + (15 * 60),
            '/api/admin',
            self::COOKIE_DOMAIN,
            true,
            true,
            false,
            Cookie::SAMESITE_NONE
        )
    );
}


public function getVerificationToken(
    Request $request
): ?string {
    return $request->cookies->get(
        'adminVerificationToken'
    );
}



public function clearVerificationToken(
    Response $response
): void {

    $response->headers->clearCookie(
        'adminVerificationToken',
        '/api/admin'
    );
}


}