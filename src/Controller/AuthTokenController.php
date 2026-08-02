<?php

namespace App\Controller;


use App\Service\RefreshTokenService;
use App\Service\TokenService;
use App\Service\CookieService;


use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

use Symfony\Component\Routing\Annotation\Route;



class AuthTokenController extends AbstractController
{


    public function __construct(
        private readonly RefreshTokenService $refreshTokenService,
        private readonly TokenService $tokenService,
        private readonly CookieService $cookieService
    )
    {

    }








    /**
     * Refresh access token
     *
     * Node:
     *
     * userTokenRefresh()
     */
    #[Route(
        '/api/user/refresh-token',
        methods:['POST']
    )]
    public function refresh(
        Request $request
    ): JsonResponse
    {



        $refreshToken =
            $this->cookieService
            ->getRefreshToken($request);




        if(!$refreshToken){


            return $this->json([

                'success'=>false,

                'message'=>'Refresh token missing'

            ],401);

        }






        $storedToken =
            $this->refreshTokenService
            ->validate(
                $refreshToken
            );





        if(!$storedToken){


            return $this->json([

                'success'=>false,

                'message'=>'Invalid refresh token'

            ],401);

        }






        /**
         * Rotate token
         */
        $newRefreshToken =
            $this->refreshTokenService
            ->rotate(
                $storedToken
            );



        $user =
            $storedToken
            ->getUser();





        $newAccessToken =
            $this->tokenService
            ->signAccessToken(
                $user
            );






        $response =
            $this->json([

                'success'=>true,

                'message'=>'Token refreshed'

            ]);





        $this->cookieService
            ->setAccessToken(
                $response,
                $newAccessToken
            );




        $this->cookieService
            ->setRefreshToken(
                $response,
                $newRefreshToken
            );





        return $response;

    }


}