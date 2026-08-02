<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Service\OTPService;
use App\Service\TokenService;
use App\Service\UserService;
use App\Service\CookieService;
use App\Service\RefreshTokenService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/user')]
class UserController extends AbstractController
{

    public function __construct(
        private readonly UserService $userService,
        private readonly OTPService $otpService,
        private readonly TokenService $tokenService,
        private readonly RefreshTokenService $refreshTokenService,
        private readonly CookieService $cookieService,
        private readonly UserRepository $userRepository,
    ) {
    }




    /**
     * REGISTER
     *
     * Node:
     * registerUser()
     */
    #[Route('/register', methods:['POST'])]
    public function register(
        Request $request
    ): JsonResponse {


        try {


            $data=json_decode(
                $request->getContent(),
                true
            );


            $user=$this->userService->register(
                $data['name'],
                $data['email'],
                $data['password'],
                $data['googleId'] ?? null,
                $data['fcmToken'] ?? null
            );



            $otp=$this->otpService->generate($user);



            /**
             * Email sending service
             * will be added next
             */


            return $this->json([

                'success'=>true,

                'message'=>'OTP sent to your email',

                'redirect'=>'/verify-otp',

                'userId'=>$user->getId()

            ],200);



        }catch(\Throwable $e){


            return $this->json([

                'success'=>false,

                'message'=>$e->getMessage()

            ],400);

        }

    }








    /**
     * LOGIN
     *
     * Node:
     * loginUser()
     */
    #[Route('/login', methods:['POST'])]
    public function login(
        Request $request
    ): JsonResponse {


        try {


            $data=json_decode(
                $request->getContent(),
                true
            );


            $user=$this->userService->login(
                $data['email'],
                $data['password']
            );



            $accessToken=
                $this->tokenService
                ->signAccessToken($user);



            $refreshToken=
                $this->refreshTokenService
                ->create($user);



            $response=$this->json([

                'success'=>true,

                'message'=>'Login successful',

                'userId'=>$user->getId(),

                'roles'=>$user->getRoles()

            ]);



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


            return $this->json([

                'success'=>false,

                'message'=>$e->getMessage()

            ],401);

        }

    }









    /**
     * VERIFY OTP
     *
     * Node:
     * verifyOTP()
     */
    #[Route('/verify-otp', methods:['POST'])]
    public function verifyOTP(
        Request $request
    ): JsonResponse {



        $data=json_decode(
            $request->getContent(),
            true
        );



        $user=$this->userRepository
            ->find(
                $data['userId']
            );



        if(!$user){

            return $this->json([

                'success'=>false,

                'message'=>'User not found'

            ],404);
        }



        $verified=
            $this->otpService
            ->verify(
                $user,
                $data['otp']
            );



        if(!$verified){

            return $this->json([

                'success'=>false,

                'message'=>'Invalid or expired OTP'

            ],400);
        }




        $accessToken=
            $this->tokenService
            ->signAccessToken($user);



        $refreshToken=
            $this->refreshTokenService
            ->create($user);




        $response=$this->json([

            'success'=>true,

            'message'=>'Email verified successfully',

            'user'=>[

                'name'=>$user->getName(),

                'email'=>$user->getEmail()

            ]

        ]);




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

    }










    /**
     * RESEND OTP
     *
     * Node:
     * resendOTP()
     */
    #[Route('/resend-otp', methods:['POST'])]
    public function resendOTP(
        Request $request
    ): JsonResponse {


        $data=json_decode(
            $request->getContent(),
            true
        );


        $user=$this->userRepository
            ->find(
                $data['userId']
            );


        if(!$user){

            return $this->json([

                'success'=>false,

                'message'=>'User not found'

            ],404);

        }




        $otp=$this->otpService
            ->resend($user);



        return $this->json([

            'success'=>true,

            'message'=>'OTP resent successfully'

        ]);

    }









    /**
     * FORGOT PASSWORD
     */
    #[Route('/forgot-password', methods:['POST'])]
    public function forgotPassword(
        Request $request
    ): JsonResponse {


        $data=json_decode(
            $request->getContent(),
            true
        );



        $user=$this->userRepository
            ->findOneBy([
                'email'=>$data['email']
            ]);



        if(!$user){

            return $this->json([

                'success'=>false,

                'message'=>'User not found'

            ],404);

        }




        $token=
            $this->userService
            ->createPasswordResetToken(
                $user
            );



        /**
         * Email reset link later
         */


        return $this->json([

            'success'=>true,

            'message'=>'Password reset link sent'

        ]);

    }









    /**
     * RESET PASSWORD
     */
    #[Route('/reset-password/{token}', methods:['POST'])]
    public function resetPassword(
        string $token,
        Request $request
    ): JsonResponse {


        $data=json_decode(
            $request->getContent(),
            true
        );



        $user=$this->userRepository
            ->findOneBy([
                'resetToken'=>$token
            ]);



        if(!$user){

            return $this->json([

                'success'=>false,

                'message'=>'Invalid token'

            ],400);

        }




        if(
            !$user->getResetTokenExpires()
            ||
            $user->getResetTokenExpires()
            <
            new \DateTimeImmutable()
        ){

            return $this->json([

                'success'=>false,

                'message'=>'Token expired'

            ],400);

        }



        $this->userService
            ->resetPassword(
                $user,
                $data['password']
            );



        return $this->json([

            'success'=>true,

            'message'=>'Password reset successful'

        ]);

    }

}