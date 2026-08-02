<?php

namespace App\Repository;

use App\Entity\RefreshToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RefreshToken>
 */
class RefreshTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefreshToken::class);
    }


    public function findValidTokens(): array
    {

        return $this->createQueryBuilder('rt')

            ->where('rt.revoked = false')

            ->andWhere(
                'rt.expiresAt > :now'
            )

            ->setParameter(
                'now',
                new \DateTimeImmutable()
            )

            ->getQuery()

            ->getResult();

    }
    

    public function findExpiredTokens(): array
    {
        return $this->createQueryBuilder('rt')
            ->where('rt.expiresAt < :now')
            ->setParameter(
                'now',
                new \DateTimeImmutable()
            )
            ->getQuery()
            ->getResult();
    }
}
