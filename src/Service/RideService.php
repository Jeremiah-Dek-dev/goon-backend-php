<?php

namespace App\Service;

use App\Entity\Ride;
use App\Entity\User;
use App\Enum\Currency;
use App\Enum\RideStatus;
use App\Enum\UserRole;
use App\Entity\Rating;
use App\Enum\BookingStatus;
use App\Repository\BookingRepository;
use App\Repository\RatingRepository;
use App\Repository\CommissionConfigRepository;
use App\Service\GoogleMapsService;
use App\Repository\RideRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class RideService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RideRepository $rideRepository,
        private readonly CommissionConfigRepository $commissionConfigRepository,
        private readonly FileUploadService $fileUploadService,
        private readonly GoogleMapsService $googleMapsService,
        private readonly BookingRepository $bookingRepository,
        private readonly RatingRepository $ratingRepository,
    ) {
    }

    /**
     * Create a ride for an authenticated driver.
     *
     * @param array<string, mixed> $data
     */
    public function addRide(
        User $driver,
        array $data,
        ?UploadedFile $image
    ): Ride {
        /*
         * ---------------------------------------------------------
         * 1. Verify authenticated user is a driver
         * ---------------------------------------------------------
         */
        if (!$this->isDriver($driver)) {
            throw new \RuntimeException(
                'Access denied: Not a driver.'
            );
        }

        /*
         * ---------------------------------------------------------
         * 2. Driver profile must exist
         * ---------------------------------------------------------
         */
        if (!$driver->getDriverProfile()) {
            throw new \RuntimeException(
                'Driver profile not found.'
            );
        }

        /*
         * ---------------------------------------------------------
         * 3. Image is mandatory
         * ---------------------------------------------------------
         */
        if (!$image) {
            throw new \InvalidArgumentException(
                'Image is required.'
            );
        }

        /*
         * ---------------------------------------------------------
         * 4. Extract fields
         * ---------------------------------------------------------
         */
        $pickup =
            $data['pickup'] ?? null;

        $destination =
            $data['destination'] ?? null;

        $description =
            trim(
                (string) (
                    $data['description'] ?? ''
                )
            );

        $selectedDate =
            trim(
                (string) (
                    $data['selectedDate'] ?? ''
                )
            );

        $selectedTime =
            trim(
                (string) (
                    $data['selectedTime'] ?? ''
                )
            );

        $type =
            trim(
                (string) (
                    $data['type'] ?? ''
                )
            );

        /*
         * ---------------------------------------------------------
         * 5. Validate locations
         * ---------------------------------------------------------
         */
        if (
            !is_array($pickup)
            || empty($pickup)
        ) {
            throw new \InvalidArgumentException(
                'Pickup location is required.'
            );
        }

        if (
            !is_array($destination)
            || empty($destination)
        ) {
            throw new \InvalidArgumentException(
                'Destination location is required.'
            );
        }

        $pickupAddress =
            trim(
                (string) (
                    $pickup['address']
                    ?? $pickup['formattedAddress']
                    ?? $pickup['label']
                    ?? ''
                )
            );

        $destinationAddress =
            trim(
                (string) (
                    $destination['address']
                    ?? $destination['formattedAddress']
                    ?? $destination['label']
                    ?? ''
                )
            );

        if ($pickupAddress === '') {
            throw new \InvalidArgumentException(
                'Pickup address is required.'
            );
        }

        if ($destinationAddress === '') {
            throw new \InvalidArgumentException(
                'Destination address is required.'
            );
        }

        /*
         * ---------------------------------------------------------
         * 6. Geocode pickup
         * ---------------------------------------------------------
         */
        $pickupLocation =
            $this->googleMapsService
                ->geocode($pickupAddress);

        /*
         * ---------------------------------------------------------
         * 7. Geocode destination
         * ---------------------------------------------------------
         */
        $destinationLocation =
            $this->googleMapsService
                ->geocode($destinationAddress);

        /*
         * ---------------------------------------------------------
         * 8. Normalize location objects
         * ---------------------------------------------------------
         */
        $pickup = [
            'address' =>
                $pickupLocation['address'],

            'latitude' =>
                $pickupLocation['latitude'],

            'longitude' =>
                $pickupLocation['longitude'],

            'placeId' =>
                $pickupLocation['placeId'],
        ];

        $destination = [
            'address' =>
                $destinationLocation['address'],

            'latitude' =>
                $destinationLocation['latitude'],

            'longitude' =>
                $destinationLocation['longitude'],

            'placeId' =>
                $destinationLocation['placeId'],
        ];

        /*
         * ---------------------------------------------------------
         * 9. Calculate route
         * ---------------------------------------------------------
         */
        $route =
            $this->googleMapsService
                ->calculateRoute(
                    $pickupLocation['latitude'],
                    $pickupLocation['longitude'],
                    $destinationLocation['latitude'],
                    $destinationLocation['longitude']
                );

        /*
         * ---------------------------------------------------------
         * 10. Validate text fields
         * ---------------------------------------------------------
         */
        if ($description === '') {
            throw new \InvalidArgumentException(
                'Description is required.'
            );
        }

        if ($selectedDate === '') {
            throw new \InvalidArgumentException(
                'Selected date is required.'
            );
        }

        if ($selectedTime === '') {
            throw new \InvalidArgumentException(
                'Selected time is required.'
            );
        }

        if ($type === '') {
            throw new \InvalidArgumentException(
                'Ride type is required.'
            );
        }

        /*
         * ---------------------------------------------------------
         * 11. Numeric validation
         * ---------------------------------------------------------
         */
        $price =
            filter_var(
                $data['price'] ?? null,
                FILTER_VALIDATE_FLOAT
            );

        $capacity =
            filter_var(
                $data['capacity'] ?? null,
                FILTER_VALIDATE_INT
            );

        $maxPassengers =
            filter_var(
                $data['maxPassengers'] ?? null,
                FILTER_VALIDATE_INT
            );

        if (
            $price === false
            || $price <= 0
        ) {
            throw new \InvalidArgumentException(
                'Ride price must be greater than zero.'
            );
        }

        if (
            $capacity === false
            || $capacity <= 0
        ) {
            throw new \InvalidArgumentException(
                'Capacity must be greater than zero.'
            );
        }

        if (
            $maxPassengers === false
            || $maxPassengers <= 0
        ) {
            throw new \InvalidArgumentException(
                'Maximum passengers must be greater than zero.'
            );
        }

        if ($maxPassengers > $capacity) {
            throw new \InvalidArgumentException(
                'Maximum passengers cannot exceed total capacity.'
            );
        }

        /*
         * ---------------------------------------------------------
         * 12. Parse date
         * ---------------------------------------------------------
         */
        try {
            $selectedDateObject =
                new \DateTimeImmutable(
                    $selectedDate
                );

        } catch (\Throwable $e) {
            throw new \InvalidArgumentException(
                'Invalid selected date.',
                0,
                $e
            );
        }

        /*
         * ---------------------------------------------------------
         * 13. Currency
         * ---------------------------------------------------------
         */
        $currencyValue =
            strtoupper(
                trim(
                    (string) (
                        $data['currency']
                        ?? 'USD'
                    )
                )
            );

        try {
            $currency =
                Currency::from(
                    $currencyValue
                );

        } catch (\ValueError $e) {
            throw new \InvalidArgumentException(
                'Invalid currency.',
                0,
                $e
            );
        }

        /*
         * ---------------------------------------------------------
         * 14. Active commission configuration
         * ---------------------------------------------------------
         */
        $commissionConfig =
            $this->commissionConfigRepository
                ->findActive();

        $rate =
            $commissionConfig
                ? (float) $commissionConfig->getRate()
                : 0.0;

        if (
            $rate < 0
            || $rate > 1
        ) {
            throw new \RuntimeException(
                'Invalid commission configuration.'
            );
        }

        /*
         * ---------------------------------------------------------
         * 15. Calculate commission
         * ---------------------------------------------------------
         */
        $commissionAmount =
            round(
                (float) $price * $rate,
                2
            );

        $payoutAmount =
            round(
                (float) $price
                - $commissionAmount,
                2
            );

        /*
         * ---------------------------------------------------------
         * 16. Normalize locations for search
         * ---------------------------------------------------------
         */
        $pickupNorm =
            $this->normalizeLocation(
                $pickup
            );

        $destinationNorm =
            $this->normalizeLocation(
                $destination
            );

        /*
         * ---------------------------------------------------------
         * 17. Upload image
         * ---------------------------------------------------------
         */
        $imageUrl =
            $this->fileUploadService
                ->uploadRideImage($image);

        /*
         * ---------------------------------------------------------
         * 18. New rides require approval
         * ---------------------------------------------------------
         */
        $status = RideStatus::SCHEDULED;

        /*
         * ---------------------------------------------------------
         * 19. Create ride
         * ---------------------------------------------------------
         */
        $ride =
            new Ride();

        $ride
            ->setPickup($pickup)
            ->setDestination($destination)
            ->setDistance($route['distanceKm'])
            ->setDuration($route['duration'])
            ->setPickupNorm($pickupNorm)
            ->setDestinationNorm($destinationNorm)
            ->setPrice((float) $price)
            ->setCurrency($currency)
            ->setCommissionRate($rate)
            ->setCommissionAmount(
                $commissionAmount
            )
            ->setPayoutAmount(
                $payoutAmount
            )
            ->setDescription($description)
            ->setSelectedDate(
                $selectedDateObject
            )
            ->setSelectedTime(
                $selectedTime
            )
            ->setCapacity(
                $capacity
            )
            ->setMaxPassengers(
                $maxPassengers
            )
            ->setImageUrl(
                $imageUrl
            )
            ->setType(
                $type
            )
            ->setStatus(
                $status
            )
            ->setDriver(
                $driver
            );

        /*
         * ---------------------------------------------------------
         * 20. Persist
         * ---------------------------------------------------------
         */
        $this->entityManager
            ->persist($ride);

        $this->entityManager
            ->flush();

        return $ride;
    }

    private function isDriver(
        User $user
    ): bool {
        foreach (
            $user->getRoleAssignments()
            as $assignment
        ) {
            if (
                $assignment->getRole()
                === UserRole::DRIVER
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalize location for searching.
     *
     * @param array<string, mixed> $location
     */
    private function normalizeLocation(
        array $location
    ): string {
        $address =
            $location['address']
            ?? $location['name']
            ?? '';

        return strtolower(
            trim(
                (string) $address
            )
        );
    }



/**
 * Search available rides.
 *
 * @param array<string, mixed> $filters
 *
 * @return array<string, mixed>
 */
public function searchRides(
    array $filters,
    int $page = 1,
    int $limit = 10
): array {
    $page = max(1, $page);
    $limit = max(1, min(100, $limit));

    $result = $this->rideRepository->searchAvailableRides(
        $filters,
        $page,
        $limit
    );

    /** @var Ride[] $rides */
    $rides = $result['rides'];

    $total = $result['total'];

    /*
     * Get approved passenger counts in ONE query.
     *
     * This avoids the N+1 query problem.
     */
    $seatsMap =
        $this->bookingRepository
            ->getApprovedPassengerCounts($rides);

    $formattedRides = [];

    foreach ($rides as $ride) {
        $rideId = $ride->getId();

        if ($rideId === null) {
            continue;
        }

        $seatsTaken = $seatsMap[$rideId] ?? 0;

        $isFull =
            $seatsTaken >= $ride->getMaxPassengers();

        $driver = $ride->getDriver();

        $formattedRides[] = [
            'id' => $rideId,

            'pickup' => $ride->getPickup(),

            'destination' => $ride->getDestination(),

            'price' => $ride->getPrice(),

            'currency' => $ride->getCurrency()->value,

            'description' => $ride->getDescription(),

            'selectedDate' =>
                $ride->getSelectedDate()->format(DATE_ATOM),

            'selectedTime' =>
                $ride->getSelectedTime(),

            'capacity' =>
                $ride->getCapacity(),

            'maxPassengers' =>
                $ride->getMaxPassengers(),

            'imageUrl' =>
                $ride->getImageUrl(),

            'type' =>
                $ride->getType(),

            'status' =>
                $ride->getStatus()->value,

            'distance' =>
                $ride->getDistance(),

            'duration' =>
                $ride->getDuration(),

            'pickupNorm' =>
                $ride->getPickupNorm(),

            'destinationNorm' =>
                $ride->getDestinationNorm(),

            'seatsTaken' =>
                $seatsTaken,

            'seatsAvailable' =>
                max(
                    0,
                    $ride->getMaxPassengers() - $seatsTaken
                ),

            'isFull' =>
                $isFull,

            'driver' => $driver
                ? [
                    'id' => $driver->getId(),
                    'name' => $driver->getName(),
                    'avatar' => $driver->getAvatar(),
                ]
                : null,

            'createdAt' =>
                $ride->getCreatedAt()->format(DATE_ATOM),

            'updatedAt' =>
                $ride->getUpdatedAt()->format(DATE_ATOM),
        ];
    }

    return [
        'rides' => $formattedRides,

        'total' => $total,

        'totalPages' =>
            (int) ceil($total / $limit),

        'page' =>
            $page,

        'limit' =>
            $limit,
    ];
}



/**
 * Get available ride counts grouped by type.
 *
 * @param array<string, mixed> $filters
 *
 * @return array<int, array{_id: string, count: int}>
 */
public function getRideCounts(
    array $filters
): array {
    return $this->rideRepository->countByType($filters);
}



/**
 * Rate a ride.
 *
 * @param array<string, mixed> $data
 */
public function rateRide(
    User $rater,
    array $data
): Rating {
    $rideId = filter_var(
        $data['rideId'] ?? null,
        FILTER_VALIDATE_INT
    );

    $score = filter_var(
        $data['score'] ?? null,
        FILTER_VALIDATE_INT
    );

    $comment = trim(
        (string) ($data['comment'] ?? '')
    );

    if ($rideId === false || $rideId <= 0) {
        throw new \InvalidArgumentException(
            'Invalid ride ID.'
        );
    }

    if ($score === false || $score < 1 || $score > 5) {
        throw new \InvalidArgumentException(
            'Rating score must be between 1 and 5.'
        );
    }

    $ride = $this->rideRepository->find($rideId);

    if (!$ride) {
        throw new \InvalidArgumentException(
            'Ride not found.'
        );
    }

    /*
     * The user must have an approved booking.
     */
    if (
        !$this->bookingRepository
            ->hasApprovedBooking($rater, $ride)
    ) {
        throw new \RuntimeException(
            'You can only rate a ride you have booked.'
        );
    }

    /*
     * Prevent duplicate ratings for the same
     * user + ride combination.
     */
    $existingRating =
        $this->ratingRepository->findOneBy([
            'ride' => $ride,
            'rater' => $rater,
        ]);

    if ($existingRating) {
        throw new \RuntimeException(
            'You have already rated this ride.'
        );
    }

    $driver = $ride->getDriver();

    if (!$driver) {
        throw new \RuntimeException(
            'This ride has no assigned driver.'
        );
    }

    $rating = new Rating();

    $rating
        ->setRide($ride)
        ->setRater($rater)
        ->setRatee($driver)
        ->setScore($score)
        ->setComment(
            $comment !== '' ? $comment : null
        );

    $this->entityManager->persist($rating);

    /*
     * Calculate the driver's average rating.
     */
    $ratings = $driver->getRatingsReceived();

    $totalScore = 0;
    $totalRatings = 0;

    foreach ($ratings as $existing) {
        $totalScore += $existing->getScore();
        $totalRatings++;
    }


    $totalScore += $score;
    $totalRatings++;

    $this->entityManager->flush();

    return $rating;
}




/**
 * Normalize a search string.
 */
private function normalizeSearchValue(
    mixed $value
): ?string {
    if ($value === null) {
        return null;
    }

    $value = trim((string) $value);

    if ($value === '') {
        return null;
    }

    /*
     * Remove accents.
     */
    $value = transliterator_transliterate(
        'NFD; [:Nonspacing Mark:] Remove; NFC',
        $value
    );

    return mb_strtolower(
        trim($value)
    );
}



/**
 * Serialize a ride for API responses.
 *
 * @return array<string, mixed>
 */
private function serializeRide(
    Ride $ride,
    int $seatsTaken,
    int $availableSeats
): array {
    $driver = $ride->getDriver();

    return [
        'id' => $ride->getId(),

        'pickup' => $ride->getPickup(),

        'destination' => $ride->getDestination(),

        'pickupNorm' => $ride->getPickupNorm(),

        'destinationNorm' =>
            $ride->getDestinationNorm(),

        'price' => $ride->getPrice(),

        'currency' =>
            $ride->getCurrency()->value,

        'description' =>
            $ride->getDescription(),

        'selectedDate' =>
            $ride
                ->getSelectedDate()
                ->format(DATE_ATOM),

        'selectedTime' =>
            $ride->getSelectedTime(),

        'capacity' =>
            $ride->getCapacity(),

        'maxPassengers' =>
            $ride->getMaxPassengers(),

        'imageUrl' =>
            $ride->getImageUrl(),

        'type' =>
            $ride->getType(),

        'status' =>
            $ride->getStatus()->value,

        'distance' =>
            $ride->getDistance(),

        'duration' =>
            $ride->getDuration(),

        'seatsTaken' =>
            $seatsTaken,

        'availableSeats' =>
            $availableSeats,

        'isFull' =>
            $availableSeats <= 0,

        'driver' => $driver
            ? [
                'id' => $driver->getId(),
                'name' => $driver->getName(),
                'email' => $driver->getEmail(),
                'avatar' => $driver->getAvatar(),
            ]
            : null,

        'createdAt' =>
            $ride
                ->getCreatedAt()
                ->format(DATE_ATOM),

        'updatedAt' =>
            $ride
                ->getUpdatedAt()
                ->format(DATE_ATOM),
    ];
}



/**
 * Get all rides created by a driver.
 *
 * @return array<int, Ride>
 */
public function getDriverRides(User $driver): array
{
    if (!$this->isDriver($driver)) {
        throw new \RuntimeException(
            'Access denied: Not a driver.'
        );
    }

    return $this->rideRepository->findBy(
        ['driver' => $driver],
        [
            'selectedDate' => 'ASC',
            'selectedTime' => 'ASC',
        ]
    );
}


/**
 * Get one ride belonging to a driver.
 */
public function getDriverRide(
    User $driver,
    int $rideId
): Ride {
    if (!$this->isDriver($driver)) {
        throw new \RuntimeException(
            'Access denied: Not a driver.'
        );
    }

    $ride = $this->rideRepository->findOneBy([
        'id' => $rideId,
        'driver' => $driver,
    ]);

    if (!$ride) {
        throw new \RuntimeException(
            'Ride not found.'
        );
    }

    return $ride;
}


/**
 * Delete a ride belonging to a driver.
 */
public function deleteDriverRide(
    User $driver,
    int $rideId
): void {
    $ride = $this->getDriverRide(
        $driver,
        $rideId
    );

    /*
     * Prevent deletion once the ride has progressed.
     *
     * A driver should not be able to remove a ride that is
     * already being used operationally.
     */
    $protectedStatuses = [
        RideStatus::ASSIGNED,
        RideStatus::IN_PROGRESS,
        RideStatus::DRIVER_EN_ROUTE,
        RideStatus::ARRIVED,
        RideStatus::COMPLETED,
    ];

    if (
        in_array(
            $ride->getStatus(),
            $protectedStatuses,
            true
        )
    ) {
        throw new \RuntimeException(
            'This ride can no longer be deleted.'
        );
    }

    $this->entityManager->remove($ride);
    $this->entityManager->flush();
}
}
