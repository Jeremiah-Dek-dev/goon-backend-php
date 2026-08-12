<?php

namespace App\Repository;

use App\Entity\ActivityLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ActivityLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityLog::class);
    }

    /**
     * Find activity logs with filtering and pagination.
     *
     * @return array{
     *     logs: ActivityLog[],
     *     total: int
     * }
     */
    public function findPaginated(
        int $page = 1,
        int $limit = 20,
        ?string $userKeyword = null,
        ?string $action = null,
        ?\DateTimeImmutable $from = null,
        ?\DateTimeImmutable $to = null,
        string $sort = 'desc'
    ): array {
        $page = max(1, $page);
        $limit = max(1, min($limit, 100));

        $offset = ($page - 1) * $limit;

        /*
         * Main query.
         */
        $qb = $this->createQueryBuilder('log')
            ->leftJoin('log.user', 'u')
            ->addSelect('u');

        /*
         * User search:
         * Search by name OR email.
         */
        if ($userKeyword !== null && $userKeyword !== '') {
            $qb
                ->andWhere(
                    $qb->expr()->orX(
                        $qb->expr()->like(
                            'LOWER(u.name)',
                            ':userKeyword'
                        ),
                        $qb->expr()->like(
                            'LOWER(u.email)',
                            ':userKeyword'
                        )
                    )
                )
                ->setParameter(
                    'userKeyword',
                    '%' . mb_strtolower($userKeyword) . '%'
                );
        }

        /*
         * Action filter.
         */
        if ($action !== null && $action !== '') {
            $qb
                ->andWhere('LOWER(log.action) LIKE :action')
                ->setParameter(
                    'action',
                    '%' . mb_strtolower($action) . '%'
                );
        }

        /*
         * Date range.
         */
        if ($from !== null) {
            $qb
                ->andWhere('log.createdAt >= :from')
                ->setParameter('from', $from);
        }

        if ($to !== null) {
            $qb
                ->andWhere('log.createdAt <= :to')
                ->setParameter('to', $to);
        }

        /*
         * Only allow known sort directions.
         */
        $direction = strtolower($sort) === 'asc'
            ? 'ASC'
            : 'DESC';

        $qb
            ->orderBy('log.createdAt', $direction)
            ->addOrderBy('log.id', $direction)
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $logs = $qb
            ->getQuery()
            ->getResult();

        /*
         * Count query.
         */
        $countQb = $this->createQueryBuilder('log')
            ->select('COUNT(log.id)')
            ->leftJoin('log.user', 'u');

        if ($userKeyword !== null && $userKeyword !== '') {
            $countQb
                ->andWhere(
                    $countQb->expr()->orX(
                        $countQb->expr()->like(
                            'LOWER(u.name)',
                            ':userKeyword'
                        ),
                        $countQb->expr()->like(
                            'LOWER(u.email)',
                            ':userKeyword'
                        )
                    )
                )
                ->setParameter(
                    'userKeyword',
                    '%' . mb_strtolower($userKeyword) . '%'
                );
        }

        if ($action !== null && $action !== '') {
            $countQb
                ->andWhere('LOWER(log.action) LIKE :action')
                ->setParameter(
                    'action',
                    '%' . mb_strtolower($action) . '%'
                );
        }

        if ($from !== null) {
            $countQb
                ->andWhere('log.createdAt >= :from')
                ->setParameter('from', $from);
        }

        if ($to !== null) {
            $countQb
                ->andWhere('log.createdAt <= :to')
                ->setParameter('to', $to);
        }

        $total = (int) $countQb
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'logs' => $logs,
            'total' => $total,
        ];
    }
}