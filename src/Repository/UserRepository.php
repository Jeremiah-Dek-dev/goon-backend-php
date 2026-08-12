<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Enum\UserRole;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }



    public function findOneByRole(UserRole $role): ?User
    {
        return $this->createQueryBuilder('u')
            ->innerJoin('u.roleAssignments', 'roleAssignment')
            ->andWhere('roleAssignment.role = :role')
            ->setParameter('role', $role)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }


        /**
     * Count users assigned to a specific role.
     */
    public function countByRole(UserRole $role): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(DISTINCT u.id)')
            ->innerJoin('u.roleAssignments', 'roleAssignment')
            ->andWhere('roleAssignment.role = :role')
            ->setParameter('role', $role)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
