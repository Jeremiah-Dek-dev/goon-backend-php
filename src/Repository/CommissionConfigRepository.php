<?php

namespace App\Repository;

use App\Entity\CommissionConfig;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CommissionConfigRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommissionConfig::class);
    }

    /**
     * Get the currently active commission configuration.
     */
    public function findActive(): ?CommissionConfig
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.active = :active')
            ->setParameter('active', true)
            ->orderBy('c.effectiveFrom', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Deactivate all currently active commission configurations.
     */
    public function deactivateAll(): void
    {
        $this->createQueryBuilder('c')
            ->update()
            ->set('c.active', ':inactive')
            ->where('c.active = :active')
            ->setParameter('inactive', false)
            ->setParameter('active', true)
            ->getQuery()
            ->execute();
    }
}