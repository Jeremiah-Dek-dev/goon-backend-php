<?php

namespace App\Controller;


use App\Service\UserService;
use App\Service\TokenService;
use App\Service\CookieService;
use App\Service\RefreshTokenService;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

use Symfony\Component\Routing\Annotation\Route;



class GoogleAuthController extends AbstractController
{


    public function __construct(
        private readonly UserService $userService,
        private readonly TokenService $tokenService,
        private readonly RefreshTokenService $refreshTokenService,
        private readonly CookieService $cookieService,
    )
    {

    }





    /**
     * Start Google Login
     *
     * Node equivalent:
     *
     * GET /google
     *
     */
    #[Route('/api/user/google', name:'google_login')]
    public function google(
        ClientRegistry $clientRegistry
    ): Response
    {


        return $clientRegistry
            ->getClient('google')
            ->redirect(
                [
                    'profile',
                    'email'
                ]
            );

    }







    /**
     * Google callback
     *
     * Node equivalent:
     *
     * googleAuthCallback()
     *
     */
    #[Route(
        '/api/user/google/callback',
        name:'google_callback'
    )]
    public function callback(
        ClientRegistry $clientRegistry
    ): Response
    {


        try {



            $googleUser =
                $clientRegistry
                ->getClient('google')
                ->fetchUser();



            /**
             * Google data
             *
             * Node:
             *
             * req.user
             */

            $email =
                $googleUser
                ->getEmail();



            $name =
                $googleUser
                ->getName();



            $googleId =
                $googleUser
                ->getId();



            $avatar =
                $googleUser
                ->getAvatar();



            /**
             * Find/Create user
             *
             * Same as:
             *
             * prisma.user.findUnique()
             */
            $user =
                $this->userService
                ->createGoogleUser(
                    $name,
                    $email,
                    $avatar,
                    $googleId
                );






            /**
             * Generate tokens
             */
            $accessToken =
                $this->tokenService
                ->signAccessToken(
                    $user
                );



            $refreshToken =
                $this->refreshTokenService
                ->create(
                    $user
                );






            /**
             * Redirect response
             */
            $response =
                new RedirectResponse(
                    $_ENV['FRONTEND_URL']
                    .'/?name='
                    .urlencode($user->getName())
                    .'&email='
                    .urlencode($user->getEmail())
                );





            /**
             * HTTP ONLY COOKIES
             */
            $this->cookieService
                ->setAccessToken(
                    $response,
                    $accessToken
                );


            $this->cookieService
                ->setRefreshToken(
                    $response,
                    $refreshToken
                );



            return $response;




        }catch(\Throwable $e){


            return new RedirectResponse(

                $_ENV['FRONTEND_URL']
                .'/login?error=google_auth_failed'

            );

        }

    }

}