<?php

namespace App\Service;


use App\Entity\User;
use App\Entity\RefreshToken;

use App\Repository\RefreshTokenRepository;

use Doctrine\ORM\EntityManagerInterface;

use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;



class RefreshTokenService
{


    private const TTL_DAYS = 7;



    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RefreshTokenRepository $repository,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly TokenService $tokenService
    )
    {

    }







    /**
     * Create refresh token
     *
     * Node:
     *
     * signRefreshToken()
     * prisma.refreshToken.create()
     */
    public function create(
        User $user
    ): string
    {


        $rawToken =
            $this->tokenService
            ->signRefreshToken($user);



        /**
         * Store hashed token
         *
         * Same as Node:
         *
         * bcrypt.hash(refreshToken)
         */
        $hashed =
            password_hash(
                $rawToken,
                PASSWORD_BCRYPT
            );




        $refreshToken =
            new RefreshToken();



        $refreshToken
            ->setUser($user)
            ->setToken($hashed)
            ->setExpiresAt(
                new \DateTimeImmutable(
                    '+' . self::TTL_DAYS . ' days'
                )
            );



        $this->entityManager
            ->persist($refreshToken);



        $this->entityManager
            ->flush();



        return $rawToken;

    }









    /**
     * Validate refresh token
     */
    public function validate(
        string $rawToken
    ): ?RefreshToken
    {



        $tokens =
            $this->repository
            ->findValidTokens();



        foreach($tokens as $token){


            if(
                password_verify(
                    $rawToken,
                    $token->getToken()
                )
            ){


                if(
                    $token->isRevoked()
                    ||
                    $token->isExpired()
                ){

                    return null;

                }



                return $token;

            }

        }



        return null;

    }









    /**
     * Rotate refresh token
     *
     * Security:
     *
     * old token dies
     * new token created
     */
    public function rotate(
        RefreshToken $oldToken
    ): string
    {



        $oldToken
            ->setRevoked(true);



        $this->entityManager
            ->flush();



        return $this->create(
            $oldToken->getUser()
        );

    }









    /**
     * Logout support
     */
    public function revoke(
        string $rawToken
    ): void
    {


        $token =
            $this->validate(
                $rawToken
            );


        if($token){

            $token->setRevoked(true);

            $this->entityManager->flush();

        }


    }



    public function revokeAll(User $user): void
    {

        foreach($user->getRefreshTokens() as $token){

            $token->setRevoked(true);

        }


        $this->entityManager->flush();

    }


}