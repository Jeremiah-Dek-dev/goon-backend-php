<?php

namespace App\Repository;

use App\Entity\OTP;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;


class OTPRepository extends ServiceEntityRepository
{

    public function __construct(
        ManagerRegistry $registry
    ) {
        parent::__construct(
            $registry,
            OTP::class
        );
    }



    public function findValidOtp(
        User $user,
        string $code
    ): ?OTP {

        return $this->createQueryBuilder('o')
            ->where('o.user = :user')
            ->andWhere('o.otp = :otp')
            ->andWhere('o.expiresAt > :now')
            ->setParameter('user',$user)
            ->setParameter('otp',$code)
            ->setParameter(
                'now',
                new \DateTimeImmutable()
            )
            ->getQuery()
            ->getOneOrNullResult();

    }



    public function deleteUserOtps(
        User $user
    ): void {


        $this->createQueryBuilder('o')
            ->delete()
            ->where('o.user = :user')
            ->setParameter('user',$user)
            ->getQuery()
            ->execute();

    }

}