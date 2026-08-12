<?php

namespace App\Repository;

use App\Entity\Booking;
use App\Entity\Ride;
use App\Entity\User;
use App\Enum\BookingStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Booking>
 */
class BookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Booking::class);
    }

    /**
     * Get total number of bookings.
     */
    public function countAll(): int
    {
        return (int) (
            $this->createQueryBuilder('b')
                ->select('COUNT(b.id)')
                ->getQuery()
                ->getSingleScalarResult()
        );
    }

    /**
     * Get total booking revenue.
     */
    public function getTotalRevenue(): float
    {
        return (float) (
            $this->createQueryBuilder('b')
                ->select('COALESCE(SUM(b.amount), 0)')
                ->getQuery()
                ->getSingleScalarResult()
        );
    }

    /**
     * Get booking count grouped by status.
     *
     * @return array<int, array{name: string, value: int}>
     */
    public function getStatusDistribution(): array
    {
        $results = $this->createQueryBuilder('b')
            ->select('b.status AS status')
            ->addSelect('COUNT(b.id) AS total')
            ->groupBy('b.status')
            ->getQuery()
            ->getArrayResult();

        $data = [];

        foreach ($results as $row) {
            $status = $row['status'];

            if ($status instanceof BookingStatus) {
                $status = $status->value;
            }

            $data[] = [
                'name' => (string) $status,
                'value' => (int) $row['total'],
            ];
        }

        return $data;
    }

    /**
     * Get monthly revenue for a given year.
     *
     * @return array<int, float>
     */
    public function getMonthlyRevenue(int $year): array
    {
        $start = new \DateTimeImmutable(
            sprintf('%d-01-01 00:00:00', $year)
        );

        $end = $start->modify('+1 year');

        $results = $this->createQueryBuilder('b')
            ->select('b.bookingDate AS bookingDate')
            ->addSelect('b.amount AS amount')
            ->andWhere('b.bookingDate >= :start')
            ->andWhere('b.bookingDate < :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getArrayResult();

        $monthlyRevenue = array_fill(1, 12, 0.0);

        foreach ($results as $row) {
            $date = $row['bookingDate'];

            if (!$date instanceof \DateTimeInterface) {
                $date = new \DateTimeImmutable($date);
            }

            $month = (int) $date->format('n');

            $monthlyRevenue[$month] += (float) $row['amount'];
        }

        return $monthlyRevenue;
    }

    /**
     * Get monthly booking counts for a given year.
     *
     * @return array<int, int>
     */
    public function getMonthlyBookings(int $year): array
    {
        $start = new \DateTimeImmutable(
            sprintf('%d-01-01 00:00:00', $year)
        );

        $end = $start->modify('+1 year');

        $results = $this->createQueryBuilder('b')
            ->select('b.bookingDate AS bookingDate')
            ->andWhere('b.bookingDate >= :start')
            ->andWhere('b.bookingDate < :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getArrayResult();

        $monthlyBookings = array_fill(1, 12, 0);

        foreach ($results as $row) {
            $date = $row['bookingDate'];

            if (!$date instanceof \DateTimeInterface) {
                $date = new \DateTimeImmutable($date);
            }

            $month = (int) $date->format('n');

            $monthlyBookings[$month]++;
        }

        return $monthlyBookings;
    }

    /**
     * Get approved passengers currently occupying a ride.
     */
    public function getApprovedPassengerCount(Ride $ride): int
    {
        return (int) (
            $this->createQueryBuilder('b')
                ->select('COALESCE(SUM(b.passengers), 0)')
                ->andWhere('b.ride = :ride')
                ->andWhere('b.status = :status')
                ->setParameter('ride', $ride)
                ->setParameter('status', BookingStatus::APPROVED)
                ->getQuery()
                ->getSingleScalarResult()
        );
    }

    /**
     * Get approved passenger counts for multiple rides.
     *
     * @param Ride[] $rides
     *
     * @return array<int, int>
     */
    public function getApprovedPassengerCounts(array $rides): array
    {
        if ($rides === []) {
            return [];
        }

        $rideIds = [];

        foreach ($rides as $ride) {
            if ($ride->getId() !== null) {
                $rideIds[] = $ride->getId();
            }
        }

        if ($rideIds === []) {
            return [];
        }

        $results = $this->createQueryBuilder('b')
            ->select('IDENTITY(b.ride) AS rideId')
            ->addSelect('COALESCE(SUM(b.passengers), 0) AS passengers')
            ->andWhere('b.ride IN (:rideIds)')
            ->andWhere('b.status = :status')
            ->setParameter('rideIds', $rideIds)
            ->setParameter('status', BookingStatus::APPROVED)
            ->groupBy('b.ride')
            ->getQuery()
            ->getArrayResult();

        $map = [];

        foreach ($results as $row) {
            $map[(int) $row['rideId']] = (int) $row['passengers'];
        }

        return $map;
    }

    /**
     * Check whether a user has already booked a ride.
     */
    public function hasUserBookedRide(
        User $user,
        Ride $ride
    ): bool {
        $count = $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->andWhere('b.user = :user')
            ->andWhere('b.ride = :ride')
            ->setParameter('user', $user)
            ->setParameter('ride', $ride)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }

    /**
     * Check whether a user has an approved booking for a ride.
     */
    public function hasApprovedBooking(
        User $user,
        Ride $ride
    ): bool {
        $count = $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->andWhere('b.user = :user')
            ->andWhere('b.ride = :ride')
            ->andWhere('b.status = :status')
            ->setParameter('user', $user)
            ->setParameter('ride', $ride)
            ->setParameter('status', BookingStatus::APPROVED)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }




    /**
 * @param int[] $rideIds
 *
 * @return array<int, int>
 */
public function getApprovedPassengerTotals(array $rideIds): array
{
    if ($rideIds === []) {
        return [];
    }

    $results = $this->createQueryBuilder('b')
        ->select('IDENTITY(b.ride) AS rideId')
        ->addSelect('COALESCE(SUM(b.passengers), 0) AS passengers')
        ->andWhere('b.ride IN (:rideIds)')
        ->andWhere('b.status = :status')
        ->setParameter('rideIds', $rideIds)
        ->setParameter('status', BookingStatus::APPROVED)
        ->groupBy('b.ride')
        ->getQuery()
        ->getArrayResult();

    $map = [];

    foreach ($results as $row) {
        $map[(int) $row['rideId']] = (int) $row['passengers'];
    }

    return $map;
}
}