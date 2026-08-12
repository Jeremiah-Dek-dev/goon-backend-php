<?php

namespace App\Repository;

use App\Entity\Ride;
use App\Enum\RideStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RideRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ride::class);
    }

    private function normalizeDate(string $date): \DateTimeImmutable
    {
        // Handles:
        // 2026-08-11
        // 2026-08-11T00:00:00.000Z
        // 2026-08-11T00:00:00Z

        return new \DateTimeImmutable($date);
    }

public function searchRides(
    ?string $pickup,
    ?string $destination,
    ?string $selectedDate,
    ?int $passengers,
    ?string $type,
    ?string $sort,
    int $page = 1,
    int $limit = 10
): array {
    $page = max(1, $page);
    $limit = max(1, min($limit, 100));

    $qb = $this->createQueryBuilder('r');

    $qb
        ->andWhere('r.status = :status')
        ->setParameter('status', RideStatus::SCHEDULED);

    /*
     * ---------------------------------------------------------
     * PICKUP
     * ---------------------------------------------------------
     */
    if ($pickup !== null && trim($pickup) !== '') {
        $pickupNorm = $this->stripAccents($pickup);

        /*
         * First try the complete normalized address.
         */
        $qb
            ->andWhere('r.pickupNorm LIKE :pickup')
            ->setParameter(
                'pickup',
                '%' . $pickupNorm . '%'
            );
    }

    /*
     * ---------------------------------------------------------
     * DESTINATION
     * ---------------------------------------------------------
     */
    if ($destination !== null && trim($destination) !== '') {
        $destinationNorm = $this->stripAccents($destination);

        $qb
            ->andWhere('r.destinationNorm LIKE :destination')
            ->setParameter(
                'destination',
                '%' . $destinationNorm . '%'
            );
    }

    /*
     * ---------------------------------------------------------
     * PASSENGERS
     * ---------------------------------------------------------
     */
    if ($passengers !== null && $passengers > 0) {
        $qb
            ->andWhere('r.maxPassengers >= :passengers')
            ->setParameter(
                'passengers',
                $passengers
            );
    }

    /*
     * ---------------------------------------------------------
     * RIDE TYPE
     * ---------------------------------------------------------
     */
    if (
        $type !== null &&
        strtolower(trim($type)) !== 'all' &&
        trim($type) !== ''
    ) {
        $qb
            ->andWhere('LOWER(r.type) = :type')
            ->setParameter(
                'type',
                strtolower(trim($type))
            );
    }

    /*
     * ---------------------------------------------------------
     * DATE
     * ---------------------------------------------------------
     */
    if ($selectedDate !== null && trim($selectedDate) !== '') {

        $date = $this->normalizeDate($selectedDate);

        $startOfDay = $date->setTime(0, 0, 0);
        $endOfDay = $date->setTime(23, 59, 59);

        $qb
            ->andWhere('r.selectedDate >= :startOfDay')
            ->andWhere('r.selectedDate <= :endOfDay')
            ->setParameter('startOfDay', $startOfDay)
            ->setParameter('endOfDay', $endOfDay);

        /*
         * IMPORTANT:
         *
         * Only hide past departure times when the searched
         * date is actually today.
         */
        $today = new \DateTimeImmutable('today');

        if (
            $date->format('Y-m-d') ===
            $today->format('Y-m-d')
        ) {
            $currentTime = (new \DateTimeImmutable())
                ->format('H:i');

            $qb
                ->andWhere(
                    'r.selectedTime >= :currentTime'
                )
                ->setParameter(
                    'currentTime',
                    $currentTime
                );
        }
    }

    /*
     * ---------------------------------------------------------
     * SORT
     * ---------------------------------------------------------
     */
    switch ($sort) {

        case 'lowestPrice':
            $qb
                ->addOrderBy('r.price', 'ASC')
                ->addOrderBy('r.selectedDate', 'ASC')
                ->addOrderBy('r.selectedTime', 'ASC');
            break;

        case 'shortestRide':
            $qb
                ->addOrderBy('r.distance', 'ASC')
                ->addOrderBy('r.selectedDate', 'ASC')
                ->addOrderBy('r.selectedTime', 'ASC');
            break;

        case 'earliest':
        default:
            $qb
                ->addOrderBy('r.selectedDate', 'ASC')
                ->addOrderBy('r.selectedTime', 'ASC');
            break;
    }

    /*
     * ---------------------------------------------------------
     * PAGINATION
     * ---------------------------------------------------------
     */
    $qb
        ->setFirstResult(($page - 1) * $limit)
        ->setMaxResults($limit);

    $rides = $qb->getQuery()->getResult();

    /*
     * ---------------------------------------------------------
     * TOTAL COUNT
     *
     * IMPORTANT:
     * This MUST contain exactly the same filters as the
     * main query.
     * ---------------------------------------------------------
     */
    $countQb = $this->createQueryBuilder('r')
        ->select('COUNT(r.id)')
        ->andWhere('r.status = :status')
        ->setParameter('status', RideStatus::SCHEDULED);

    /*
     * Pickup
     */
    if ($pickup !== null && trim($pickup) !== '') {
        $countQb
            ->andWhere('r.pickupNorm LIKE :pickup')
            ->setParameter(
                'pickup',
                '%' . $this->stripAccents($pickup) . '%'
            );
    }

    /*
     * Destination
     */
    if ($destination !== null && trim($destination) !== '') {
        $countQb
            ->andWhere('r.destinationNorm LIKE :destination')
            ->setParameter(
                'destination',
                '%' . $this->stripAccents($destination) . '%'
            );
    }

    /*
     * Passengers
     */
    if ($passengers !== null && $passengers > 0) {
        $countQb
            ->andWhere('r.maxPassengers >= :passengers')
            ->setParameter(
                'passengers',
                $passengers
            );
    }

    /*
     * Type
     */
    if (
        $type !== null &&
        strtolower(trim($type)) !== 'all' &&
        trim($type) !== ''
    ) {
        $countQb
            ->andWhere('LOWER(r.type) = :type')
            ->setParameter(
                'type',
                strtolower(trim($type))
            );
    }

    /*
     * Date
     */
    if ($selectedDate !== null && trim($selectedDate) !== '') {

        $date = $this->normalizeDate($selectedDate);

        $startOfDay = $date->setTime(0, 0, 0);
        $endOfDay = $date->setTime(23, 59, 59);

        $countQb
            ->andWhere('r.selectedDate >= :startOfDay')
            ->andWhere('r.selectedDate <= :endOfDay')
            ->setParameter('startOfDay', $startOfDay)
            ->setParameter('endOfDay', $endOfDay);

        $today = new \DateTimeImmutable('today');

        if (
            $date->format('Y-m-d') ===
            $today->format('Y-m-d')
        ) {
            $countQb
                ->andWhere(
                    'r.selectedTime >= :currentTime'
                )
                ->setParameter(
                    'currentTime',
                    (new \DateTimeImmutable())->format('H:i')
                );
        }
    }

    $total = (int) $countQb
        ->getQuery()
        ->getSingleScalarResult();

    return [
        'rides' => $rides,
        'total' => $total,
    ];
}



    private function stripAccents(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $value = iconv(
            'UTF-8',
            'ASCII//TRANSLIT//IGNORE',
            $value
        );

        return strtolower(trim($value ?: ''));
    }





    /**
 * Get available ride counts grouped by type.
 *
 * @param array<string, mixed> $filters
 *
 * @return array<int, array{_id: string, count: int}>
 */
public function countByType(array $filters = []): array
{
    $qb = $this->createQueryBuilder('r')
        ->select('r.type AS type')
        ->addSelect('COUNT(r.id) AS count')
        ->andWhere('r.status = :status')
        ->setParameter('status', RideStatus::SCHEDULED)
        ->groupBy('r.type');

    if (!empty($filters['pickup'])) {
        $qb
            ->andWhere('r.pickupNorm LIKE :pickup')
            ->setParameter(
                'pickup',
                '%' . $this->stripAccents((string) $filters['pickup']) . '%'
            );
    }

    if (!empty($filters['destination'])) {
        $qb
            ->andWhere('r.destinationNorm LIKE :destination')
            ->setParameter(
                'destination',
                '%' . $this->stripAccents((string) $filters['destination']) . '%'
            );
    }

    if (!empty($filters['selectedDate'])) {
        $date = new \DateTimeImmutable(
            (string) $filters['selectedDate']
        );

        $startOfDay = new \DateTimeImmutable(
            $date->format('Y-m-d') . ' 00:00:00'
        );

        $endOfDay = new \DateTimeImmutable(
            $date->format('Y-m-d') . ' 23:59:59'
        );

        $qb
            ->andWhere('r.selectedDate >= :startOfDay')
            ->andWhere('r.selectedDate <= :endOfDay')
            ->setParameter('startOfDay', $startOfDay)
            ->setParameter('endOfDay', $endOfDay);

        // Don't count rides that have already departed today.
        $today = new \DateTimeImmutable('today');

        if ($startOfDay->format('Y-m-d') === $today->format('Y-m-d')) {
            $qb
                ->andWhere('r.selectedTime >= :currentTime')
                ->setParameter(
                    'currentTime',
                    (new \DateTimeImmutable())->format('H:i')
                );
        }
    }

    if (!empty($filters['passengers'])) {
        $qb
            ->andWhere('r.maxPassengers >= :passengers')
            ->setParameter(
                'passengers',
                (int) $filters['passengers']
            );
    }

    $results = $qb
        ->getQuery()
        ->getArrayResult();

    return array_map(
        static function (array $row): array {
            return [
                '_id' => (string) $row['type'],
                'count' => (int) $row['count'],
            ];
        },
        $results
    );
}
}