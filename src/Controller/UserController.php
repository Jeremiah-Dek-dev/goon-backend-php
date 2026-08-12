<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Entity\User;
use App\Service\OTPService;
use App\Service\EmailService;
use App\Service\TokenService;
use App\Service\UserService;
use App\Service\CookieService;
use App\Service\RefreshTokenService;
use App\Service\VerificationSessionService;
use App\Service\ActivityLogService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;


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
        private readonly ActivityLogService $activityLogService,
        private readonly EmailService $emailService,
        private readonly VerificationSessionService $verificationService
    ) {
    }




    /**
     * REGISTER
     *
     * Node:
     * registerUser()
     */
    #[Route('/register', name: 'user_register', methods:['POST'])]
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



            $verificationToken =
                $this->verificationService
                ->create($user);


            $otp=$this->otpService->generate($user);


            $this->emailService->sendOTP(
                $user,
                $otp
            );


            $response = $this->json([

                'success'=>true,

                'message'=>'OTP sent to your email',

                'redirect'=>'/verify-otp',

            ]);


            $response->headers->setCookie(
                new Cookie(
                    'verify_session',
                    $verificationToken,
                    time()+900,
                    '/',
                    null,
                    true,
                    true,
                    false,
                    'None'
                )
            );

                          /*
         * Activity log.
         */
        $this->activityLogService->log(
            user: $user,
            action: 'USER_REGISTERED_SUCCESSFULLY',
            description: sprintf(
                '%s User account registered successfully and redirected to verification.',
                $user->getEmail()
            )
        );
            return $response;


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
#[Route('/login', name: 'user_login', methods:['POST'])]
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



        /**
         * User registered but never verified
                */
        if(!$user->isVerified()){


            $verificationToken =
                $this->verificationService
                ->refresh($user);



            $otp=$this->otpService
                ->generate($user);



            $this->emailService
                ->sendOTP(
                    $user,
                    $otp
                );



            $response=$this->json([

                'success'=>false,

                'message'=>'Email verification required',

                'redirect'=>'/verify-otp',

            ],403);



            $response->headers->setCookie(
                new Cookie(
                    'verify_session',
                    $verificationToken,
                    time()+900,
                    '/',
                    null,
                    true,
                    true,
                    false,
                    'None'
                )
            );


            return $response;

        }


        $accessToken =
            $this->tokenService
            ->signAccessToken($user);


        $refreshToken =
            $this->refreshTokenService
            ->create($user);

        $response=$this->json([

            'success'=>true,

            'message'=>'Login successful',

            'roles'=>$user->getRoles()

        ]);



        $this->cookieService
            ->setAuthCookies(
                $response,
                $accessToken,
                $refreshToken
            );

                      /*
         * Activity log.
         */
        $this->activityLogService->log(
            user: $user,
            action: 'USER_LOGIN_SUCCESSFULLY',
            description: sprintf(
                'Login successfully by %s.',
                $user->getEmail()
            )
        );

        $this->emailService
            ->sendWelcome(
                $user
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
    #[Route('/verify-otp', name: 'user_verify_otp', methods:['POST'])]
    public function verifyOTP(
        Request $request
    ): JsonResponse {



        $data=json_decode(
            $request->getContent(),
            true
        );



        $token = $request->cookies->get('verify_session');

        $user = $this->verificationService
             ->findUserByToken($token);



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


                $this->emailService
            ->sendWelcome(
                $user
            );



        $response=$this->json([

            'success'=>true,

            'message'=>'Email verified successfully',

            'redirect' => '/',

            'user'=>[

                'name'=>$user->getName(),

                'email'=>$user->getEmail()

            ]

        ]);


        $this->verificationService
             ->delete($token);

        $response->headers->clearCookie(
            'verify_session',
            '/',
            null
        );



        $this->cookieService
            ->setAuthCookies(
                $response,
                $accessToken,
                $refreshToken
            );

          /*
         * Activity log.
         */
        $this->activityLogService->log(
            user: $user,
            action: 'USER_VERIFIED_OTP_SUCCESSFULLY',
            description: sprintf(
                'OTP verified successfully by %s.',
                $user->getEmail()
            )
        );

        return $response;

    }










    /**
     * RESEND OTP
     *
     * Node:
     * resendOTP()
     */
    #[Route('/resend-otp', name: 'user_resend_otp', methods:['POST'])]
    public function resendOTP(
        Request $request
    ): JsonResponse {


        $data=json_decode(
            $request->getContent(),
            true
        );


        $token = $request->cookies->get('verify_session');

        $user = $this->verificationService
             ->findUserByToken($token);


        if(!$user){

            return $this->json([

                'success'=>false,

                'message'=>'User not found'

            ],404);

        }




        $otp=$this->otpService
            ->resend($user);

        
        $this->emailService
            ->sendOTP(
                $user,
                $otp
            );


                    /*
         * Activity log.
         */
        $this->activityLogService->log(
            user: $user,
            action: 'USER_REQUEST_RESEND_OTP',
            description: sprintf(
                'OTP resend to %s.',
                $user->getEmail()
            )
        );


        return $this->json([

            'success'=>true,

            'message'=>'OTP resent successfully'

        ]);

    }







#[Route('/forgot-password', name: 'user_forgot_password', methods: ['POST'])]
public function forgotPassword(
    Request $request
): JsonResponse {
    $data = json_decode(
        $request->getContent(),
        true
    );

    $email = strtolower(
        trim($data['email'] ?? '')
    );

    if ($email === '') {
        return $this->json([
            'message' => 'Email is required.',
        ], Response::HTTP_BAD_REQUEST);
    }

    try {

        /*
         * Generate and store the reset token.
         */
        $result = $this->userService
            ->requestPasswordReset($email);

        /*
         * Only send an email when a valid user
         * account was found.
         */
        if ($result['sent'] === true) {

            $resetLink = sprintf(
                '%s/auth/reset-password/%s',
                rtrim(
                    $this->frontendUrl,
                    '/'
                ),
                $result['token']
            );

            /*
             * Send password reset email.
             */
            $this->emailService
                ->sendUserPasswordReset(
                    $result['user'],
                    $resetLink,
                    $result['expiresAt']
                );

            /*
             * Activity log.
             */
            $this->activityLogService->log(
                user: $result['user'],
                action: 'REQUEST_USER_RESET_PASSWORD',
                description: sprintf(
                    'User password reset email sent to %s.',
                    $result['user']->getEmail()
                )
            );
        }

        /*
         * Do not reveal whether the account exists.
         */
        return $this->json([
            'message' =>
                'If an account exists for that email, '
                . 'a password reset link has been sent.',
        ]);

    } catch (\Throwable $e) {

        /*
         * Log the failure.
         */
        try {

            $this->activityLogService->log(
                user: null,
                action: 'REQUEST_USER_RESET_PASSWORD_FAILED',
                description: sprintf(
                    'Failed to process user password reset request for %s. Error: %s',
                    $email,
                    $e->getMessage()
                )
            );

        } catch (\Throwable $logException) {
            /*
             * Never allow logging to mask
             * the original exception.
             */
        }

        return $this->json([
            'message' =>
                'Unable to process password reset request.',
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}





#[Route('/reset-password/{token}', name: 'admin_reset_password', methods: ['POST'])]
public function resetPassword(
    string $token,
    Request $request
): JsonResponse {
    $data = json_decode(
        $request->getContent(),
        true
    );

    $newPassword = $data['newPassword'] ?? '';

    if ($newPassword === '') {
        return $this->json([
            'message' => 'New password is required.',
        ], Response::HTTP_BAD_REQUEST);
    }

    try {

        $user = $this->userService->resetPassword(
            $token,
            $newPassword
        );

        /*
         * Log successful reset here if your existing
         * ActivityLog architecture is available.
         */

        return $this->json([
            'message' => 'Password reset successful.',
        ]);

    } catch (\RuntimeException $e) {

        return $this->json([
            'message' => $e->getMessage(),
        ], Response::HTTP_BAD_REQUEST);

    } catch (\Throwable $e) {

        return $this->json([
            'message' => 'Unable to reset password.',
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}





    #[Route('/me', name: 'user_me', methods:['GET'])]
    public function me(
        #[CurrentUser] ?User $user
    ): JsonResponse {

        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        return $this->json([
            'success' => true,
            'user' => [
                'id' => $user->getId(),
                'name' => $user->getName(),
                'email' => $user->getEmail(),
                'roles' => $user->getRoles(),
                'avatar' => $user->getAvatar(),
                'verified' => $user->isVerified()
            ]
        ]);
    }



    #[Route('/refresh-token', name: 'user_refresh_token', methods:['POST'])]
    public function refreshToken(
        Request $request
    ): JsonResponse {


        $rawToken = $request
            ->cookies
            ->get('userRefreshToken');


        if(!$rawToken){

            return $this->json([
                'success'=>false,
                'message'=>'Refresh token missing'
            ],401);

        }



        $storedToken =
            $this->refreshTokenService
            ->validate($rawToken);



        if(!$storedToken){

            return $this->json([
                'success'=>false,
                'message'=>'Invalid refresh token'
            ],401);

        }



        $user =
            $storedToken->getUser();



        // rotate token
        $newRefreshToken =
            $this->refreshTokenService
            ->rotate($storedToken);



        $newAccessToken =
            $this->tokenService
            ->signAccessToken($user);



        $response=$this->json([

            'success'=>true,

            'message'=>'Token refreshed'

        ]);



        $this->cookieService
            ->setAuthCookies(
                $response,
                $newAccessToken,
                $newRefreshToken
            );



        return $response;

    }




    #[Route('/logout', name: 'user_logout', methods:['POST'])]
    public function logout(Request $request): JsonResponse
    {
        $response = new JsonResponse([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);

        $this->cookieService->clearAuthCookies($response);

        return $response;
    }

}