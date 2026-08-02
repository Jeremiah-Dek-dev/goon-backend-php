<?php

namespace App\Repository;

use App\Entity\UserRoleAssignment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserRoleAssignment>
 */
class UserRoleAssignmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserRoleAssignment::class);
    }
}
