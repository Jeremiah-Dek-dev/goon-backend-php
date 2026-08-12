<?php

namespace App\Repository;

use App\Entity\Rating;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Rating>
 */
class RatingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Rating::class);
    }


    public function findByRideAndRater(
    int $rideId,
    int $raterId
): ?Rating {
    return $this->createQueryBuilder('r')
        ->andWhere('IDENTITY(r.ride) = :rideId')
        ->andWhere('IDENTITY(r.rater) = :raterId')
        ->setParameter('rideId', $rideId)
        ->setParameter('raterId', $raterId)
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();
}
}
