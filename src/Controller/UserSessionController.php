<?php

namespace App\Controller;


use App\Repository\UserRepository;
use App\Service\CookieService;
use App\Service\RefreshTokenService;


use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

use Symfony\Component\Routing\Annotation\Route;



#[Route('/api/user')]
class UserSessionController extends AbstractController
{


    public function __construct(
        private readonly RefreshTokenService $refreshTokenService,
        private readonly CookieService $cookieService,
        private readonly UserRepository $userRepository
    )
    {

    }






    /**
     * LOGOUT
     *
     * Node:
     *
     * userLogout()
     */
    #[Route(
        '/logout',
        methods:['POST']
    )]
    public function logout(
        Request $request
    ): JsonResponse
    {


        $refreshToken =
            $request
            ->cookies
            ->get(
                'userRefreshToken'
            );



        if($refreshToken){

            $this->refreshTokenService
                ->revoke(
                    $refreshToken
                );

        }





        $response =
            $this->json([

                'success'=>true,

                'message'=>'Logged out successfully'

            ]);




        /**
         * Remove browser cookies
         */
        $this->cookieService
            ->clearAuthCookies(
                $response
            );




        return $response;

    }









    /**
     * Logout everywhere
     *
     * Extra production feature
     */
    #[Route(
        '/logout-all',
        methods:['POST']
    )]
    public function logoutAll(): JsonResponse
    {


        /** 
         * Current authenticated user
         */
        $user =
            $this->getUser();



        if(!$user){

            return $this->json([

                'success'=>false,

                'message'=>'Unauthorized'

            ],401);

        }



        $this->refreshTokenService
            ->revokeAll($user);



        $response =
            $this->json([

                'success'=>true,

                'message'=>'All sessions revoked'

            ]);



        $this->cookieService
            ->clearAuthCookies(
                $response
            );



        return $response;

    }









    /**
     * CURRENT USER
     *
     * Node:
     *
     * getUserProfile()
     *
     * GET /me
     */
    #[Route(
        '/me',
        methods:['GET']
    )]
    public function me(): JsonResponse
    {


        $user =
            $this->getUser();



        if(!$user){


            return $this->json([

                'success'=>false,

                'message'=>'Unauthorized'

            ],401);

        }






        return $this->json([

            'success'=>true,


            'user'=>[

                'id'=>$user->getId(),

                'name'=>$user->getName(),

                'email'=>$user->getEmail(),

                'avatar'=>$user->getAvatar(),

                'verified'=>$user->isVerified(),

                'roles'=>$user->getRoles(),

                'lastLoginAt'=>$user->getLastLoginAt(),

                'createdAt'=>$user->getCreatedAt()

            ]

        ]);

    }


}