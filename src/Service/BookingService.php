<?php

namespace App\Service;

use App\Entity\Booking;
use App\Entity\User;
use App\Enum\BookingStatus;
use App\Enum\Currency;
use App\Enum\RideStatus;
use App\Repository\BookingRepository;
use App\Repository\RideRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Yabacon\Paystack;

/**
 * Ports controllers/BookingController.js in full (placeBookings,
 * verifyBookings, userBookings, listBookings, updateStatus, cancelBooking).
 * No BookingRoute.js was provided, so none of these are wired to routes
 * yet except cancelBooking (called from UserController, which already has
 * a route for it from the Node UserRoute.js). Once BookingRoute.js is
 * available, a thin BookingController with #[Route] attributes calling
 * into this service is a ~5-minute job.
 *
 * FLAGS:
 * - Node's placeBookings sets `pickupLocation: {lat: master.locationLat,
 *   lng: master.locationLng}` on each booking-ride snapshot, but the Ride
 *   Prisma model you gave me in Phase 1 has NO locationLat/locationLng
 *   fields (only DriverProfile does). That line would produce
 *   `{lat: undefined, lng: undefined}` in the running Node app. Omitted
 *   here rather than guessing what it should read from - confirm intent.
 * - `io.to(driverRoom).emit(...)` / `io.to(userId).emit(...)` are ported to
 *   per-driver / per-user Mercure topics. Exact topic naming is a
 *   placeholder (see constants below) - finalize during the real-time phase.
 * - yabacon/paystack-php (the PHP Paystack SDK) throws an exception on
 *   failed initialization rather than returning `{status: false}` like
 *   Node's paystack-api - adapted via try/catch, not a 1:1 port.
 */
class BookingService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BookingRepository $bookingRepository,
        private readonly RideRepository $rideRepository,
        #private readonly HubInterface $hub,
        private readonly string $paystackSecretKey, // %env(PAYSTACK_SECRET_KEY)%
        private readonly string $frontendUrl, // %env(FRONTEND_URL)%
    ) {
    }

    /**
     * @param array<int, array{id?: mixed, _id?: mixed, passengers?: int}> $requestedRides
     * @param array<string, mixed> $address
     *
     * @return array{success: bool, message?: string, authorization_url?: string, statusCode: int}
     */
    public function placeBooking(
        User $user,
        array $requestedRides,
        float $amount,
        array $address,
        string $email,
        string $currency = 'USD',
    ): array {
        $rideIds = [];
        foreach ($requestedRides as $r) {
            $raw = $r['id'] ?? $r['_id'] ?? null;
            if ($raw !== null && preg_match('/^\d+$/', (string) $raw) === 1) {
                $rideIds[] = (int) $raw;
            }
        }

        if (count($rideIds) === 0) {
            return [
                'success' => false,
                'message' => 'Invalid or missing ride IDs — ensure ride IDs are numeric and included in request.',
                'statusCode' => 400,
            ];
        }

        $masterRides = $this->rideRepository->findBy(['id' => $rideIds]);
        $mastersById = [];
        foreach ($masterRides as $master) {
            $mastersById[$master->getId()] = $master;
        }

        $bookingRides = [];
        try {
            foreach ($requestedRides as $r) {
                $rideId = (int) ($r['id'] ?? $r['_id'] ?? 0);
                $master = $mastersById[$rideId] ?? null;
                if ($master === null) {
                    throw new \RuntimeException("Ride {$rideId} not found");
                }
                $passengers = (int) ($r['passengers'] ?? 0);
                if ($passengers > $master->getMaxPassengers()) {
                    throw new \RuntimeException(
                        "Requested {$passengers} seats exceeds limit of {$master->getMaxPassengers()} for ride {$rideId}"
                    );
                }

                $bookingRides[] = [
                    'id' => $master->getId(),
                    'pickup' => $master->getPickup(),
                    'destination' => $master->getDestination(),
                    'price' => $master->getPrice(),
                    'currency' => $master->getCurrency()->value,
                    'description' => $master->getDescription(),
                    'selectedDate' => $master->getSelectedDate()->format(\DateTimeInterface::ATOM),
                    'selectedTime' => $master->getSelectedTime(),
                    'passengers' => $passengers,
                    'imageUrl' => $master->getImageUrl(),
                    'type' => $master->getType(),
                    'status' => 'pending approval',
                    'driverId' => $master->getDriver()?->getId(),
                    // pickupLocation omitted - see class docblock FLAGS.
                ];
            }
        } catch (\RuntimeException $e) {
            $isCapacityErr = str_contains($e->getMessage(), 'exceeds limit');

            return [
                'success' => false,
                'message' => $isCapacityErr ? $e->getMessage() : 'Error placing booking.',
                'statusCode' => $isCapacityErr ? 400 : 500,
            ];
        }

        $booking = new Booking();
        $booking->setUser($user);
        $booking->setRides($bookingRides);
        $booking->setAmount($amount);
        $booking->setCurrency(Currency::from($currency));
        $booking->setAddress($address);
        $booking->setEmail($email);
        $this->em->persist($booking);

        foreach ($user->getCartItems() as $cartItem) {
            $this->em->remove($cartItem);
        }

        $this->em->flush();
        /*
        foreach ($bookingRides as $ride) {
            if ($ride['driverId'] !== null) {
                $this->hub->publish(new Update(
                    $this->driverTopic($ride['driverId']),
                    json_encode(['event' => 'rideRequest', 'bookingId' => $booking->getId(), 'ride' => $ride], JSON_THROW_ON_ERROR)
                ));
            }
        }
        */
        try {
            $paystack = new Paystack($this->paystackSecretKey);
            $tranx = $paystack->transaction->initialize([
                'email' => $email,
                'amount' => (int) ($amount * 100),
                'callback_url' => "{$this->frontendUrl}/verify?success=true&bookingId={$booking->getId()}",
                'cancel_url' => "{$this->frontendUrl}/verify?success=false&bookingId={$booking->getId()}",
            ]);

            return [
                'success' => true,
                'authorization_url' => $tranx->data->authorization_url,
                'statusCode' => 200,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Error initializing payment', 'statusCode' => 500];
        }
    }

    /**
     * @return array{success: bool, message?: string, booking?: Booking, statusCode: int}
     */
    public function verifyBooking(int $bookingId, bool $success): array
    {
        if ($success) {
            $booking = $this->bookingRepository->find($bookingId);
            if ($booking === null) {
                return ['success' => false, 'message' => 'Booking not found', 'statusCode' => 404];
            }

            $booking->setPayment(true);

            foreach ($booking->getRides() as $ride) {
                if (!in_array($ride['status'] ?? null, ['approved', 'declined'], true)) {
                    $rideEntity = $this->rideRepository->find($ride['id']);
                    if ($rideEntity !== null) {
                        $rideEntity->setStatus(RideStatus::PENDING_APPROVAL);
                    }
                    /*
                    if (($ride['driverId'] ?? null) !== null) {
                        $this->hub->publish(new Update(
                            $this->driverTopic($ride['driverId']),
                            json_encode(['event' => 'rideRequest', 'bookingId' => $bookingId, 'ride' => $ride], JSON_THROW_ON_ERROR)
                        ));
                    }
                        */
                }
            }

            $this->em->flush();

            return ['success' => true, 'message' => 'Payment successful', 'booking' => $booking, 'statusCode' => 200];
        }

        $booking = $this->bookingRepository->find($bookingId);
        if ($booking !== null) {
            $this->em->remove($booking);
            $this->em->flush();
        }

        return ['success' => false, 'message' => 'Payment failed', 'statusCode' => 200];
    }

    /**
     * @return list<Booking>
     */
    public function getUserBookings(int $userId): array
    {
        return $this->bookingRepository->createQueryBuilder('b')
            ->andWhere('b.user = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('b.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Booking>
     */
    public function listAllBookings(): array
    {
        return $this->bookingRepository->createQueryBuilder('b')
            ->orderBy('b.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function updateStatus(int $bookingId, BookingStatus $status): void
    {
        $booking = $this->bookingRepository->find($bookingId);
        if ($booking === null) {
            throw new \RuntimeException('Booking not found');
        }
        $booking->setStatus($status);
        $this->em->flush();
    }

    /**
     * @return array{success: bool, message: string, statusCode: int}
     */
    public function cancelBooking(int $bookingId, User $requestingUser): array
    {
        $booking = $this->bookingRepository->find($bookingId);

        if ($booking === null || $booking->getUser()->getId() !== $requestingUser->getId()) {
            return ['success' => false, 'message' => 'Not authorized', 'statusCode' => 403];
        }

        $updatedRides = array_map(static function (array $r): array {
            if (in_array($r['status'] ?? null, ['pending approval', 'approved'], true)) {
                $r['status'] = 'cancelled';
            }

            return $r;
        }, $booking->getRides());

        $booking->setRides($updatedRides);
        $booking->setStatus(BookingStatus::CANCELLED);
        $this->em->flush();
        /*
        foreach ($updatedRides as $r) {
            if (($r['driverId'] ?? null) !== null) {
                $this->hub->publish(new Update(
                    $this->driverTopic($r['driverId']),
                    json_encode(['event' => 'rideCancelled', 'rideId' => $r['id']], JSON_THROW_ON_ERROR)
                ));
            }
        }
            
        $this->hub->publish(new Update(
            $this->userTopic($requestingUser->getId()),
            json_encode(['event' => 'bookingCancelled', 'bookingId' => $bookingId], JSON_THROW_ON_ERROR)
        ));
            */
        return ['success' => true, 'message' => 'Booking and rides cancelled', 'statusCode' => 200];
    }

    private function driverTopic(int $driverId): string
    {
        return "https://goon.app/drivers/{$driverId}";
    }

    private function userTopic(int $userId): string
    {
        return "https://goon.app/users/{$userId}";
    }
}
