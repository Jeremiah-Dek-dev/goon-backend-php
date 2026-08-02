<?php

namespace App\Security;

use App\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Core\Exception\AuthenticationException;


class CookieJwtAuthenticator extends AbstractAuthenticator
{

    public function __construct(
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly UserRepository $userRepository
    ) {
    }



    public function supports(Request $request): ?bool
    {

        return $request->cookies->has(
            'userAccessToken'
        );

    }




    public function authenticate(Request $request): Passport
    {

        $token =
            $request->cookies->get(
                'userAccessToken'
            );


        if (!$token) {

            throw new AuthenticationException(
                'Missing access token'
            );
        }



        try {


            $payload =
                $this->jwtManager->parse(
                    $token
                );


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



        $userId =
            $payload['id'];



        return new SelfValidatingPassport(

            new UserBadge(

                (string)$userId,

                function($userIdentifier) {


                    return $this
                        ->userRepository
                        ->find($userIdentifier);

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


        return new JsonResponse(
            [
                'success'=>false,
                'message'=>'Authentication failed'
            ],
            Response::HTTP_UNAUTHORIZED
        );

    }

}