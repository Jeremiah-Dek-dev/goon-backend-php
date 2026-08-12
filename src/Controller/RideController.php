<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Ride;
use App\Service\RideService;
use App\Repository\BookingRepository;
use App\Repository\RideRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/api/rides')]
class RideController extends AbstractController
{
    public function __construct(
        private readonly RideService $rideService,
        private readonly RideRepository $rideRepository,
        private readonly BookingRepository $bookingRepository,
    ) {
    }

    /**
     * Search available rides.
     *
     * GET /api/rides/search
     */
    #[Route('/search', name: 'ride_search', methods: ['GET'])]
public function searchRides(Request $request): JsonResponse
{
    try {
        $pickup = $request->query->get('pickup');
        $destination = $request->query->get('destination');
        $selectedDate = $request->query->get('selectedDate');
        $passengers = $request->query->get('passengers');
        $sort = $request->query->get('sort');
        $filter = $request->query->get('filter');

        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, $request->query->getInt('limit', 10));

        $result = $this->rideRepository->searchRides(
            $pickup,
            $destination,
            $selectedDate,
            $passengers !== null ? (int) $passengers : null,
            $filter,
            $sort,
            $page,
            $limit
        );

        $rides = $result['rides'];
        $total = $result['total'];

        /*
         * Calculate approved passengers per ride.
         */
        $rideIds = array_map(
            static fn (Ride $ride) => $ride->getId(),
            $rides
        );

        $seatsMap = $this->bookingRepository
            ->getApprovedPassengerTotals($rideIds);

        $formattedRides = array_map(
            function (Ride $ride) use ($seatsMap) {
                $rideId = $ride->getId();

                return $this->serializeRide(
                    $ride,
                    $seatsMap[$rideId] ?? 0
                );
            },
            $rides
        );

        return $this->json([
            'success' => true,
            'rides' => $formattedRides,
            'totalPages' => (int) ceil($total / $limit),
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
        ]);
    } catch (\Throwable $e) {
        error_log('[RIDE SEARCH] ' . $e->getMessage());

        return $this->json([
            'success' => false,
            'message' => 'Server error',
        ], 500);
    }
}



    private function serializeRide(Ride $ride, int $seatsTaken = 0): array
{
    $pickup = $ride->getPickup();
    $destination = $ride->getDestination();

    $isFull = $seatsTaken >= $ride->getMaxPassengers();

    return [
        'id' => $ride->getId(),

        'pickup' => $pickup['address'] ?? '',
        'destination' => $destination['address'] ?? '',

        'pickupLocation' => [
            'coordinates' => [
                (float) ($pickup['longitude'] ?? 0),
                (float) ($pickup['latitude'] ?? 0),
            ],
        ],

        'destinationLocation' => [
            'coordinates' => [
                (float) ($destination['longitude'] ?? 0),
                (float) ($destination['latitude'] ?? 0),
            ],
        ],

        'pickupData' => $pickup,
        'destinationData' => $destination,

        'price' => $ride->getPrice(),
        'currency' => $ride->getCurrency()->value,

        'commissionRate' => $ride->getCommissionRate(),
        'commissionAmount' => $ride->getCommissionAmount(),
        'payoutAmount' => $ride->getPayoutAmount(),

        'description' => $ride->getDescription(),

        'selectedDate' => $ride->getSelectedDate()->format('Y-m-d'),
        'selectedTime' => $ride->getSelectedTime(),

        'capacity' => $ride->getCapacity(),
        'maxPassengers' => $ride->getMaxPassengers(),

        'imageUrl' => $ride->getImageUrl(),

        'type' => $ride->getType(),
        'status' => $ride->getStatus()->value,

        'distance' => $ride->getDistance(),
        'duration' => $ride->getDuration(),

        'seatsTaken' => $seatsTaken,
        'isFull' => $isFull,

        'driver' => $ride->getDriver()
            ? [
                'id' => $ride->getDriver()->getId(),
                'name' => $ride->getDriver()->getName(),
                'avatar' => $ride->getDriver()->getAvatar(),
            ]
            : null,

        'createdAt' => $ride->getCreatedAt()->format(\DateTimeInterface::ATOM),
        'updatedAt' => $ride->getUpdatedAt()->format(\DateTimeInterface::ATOM),
    ];
}


    /**
     * Get ride counts grouped by type.
     *
     * GET /api/rides/rideCounts
     */
    #[Route(
        '/rideCounts',
        name: 'ride_counts',
        methods: ['GET']
    )]
    public function getRideCounts(
        Request $request
    ): JsonResponse {
        try {
            $filters = [
                'pickup' =>
                    $request->query->get('pickup'),

                'destination' =>
                    $request->query->get('destination'),

                'selectedDate' =>
                    $request->query->get('selectedDate'),

                'passengers' =>
                    $request->query->get('passengers'),
            ];

            $counts =
                $this->rideService
                    ->getRideCounts($filters);

            return $this->json([
                'success' => true,
                'counts' => $counts,
            ]);
        } catch (\Throwable $e) {
            error_log(sprintf(
                '[RIDE COUNTS] %s in %s:%d',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ));

            return $this->json([
                'success' => false,
                'message' =>
                    'Unable to retrieve ride counts.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Rate a ride.
     *
     * POST /api/rides/rate
     */
    #[Route(
        '/rate',
        name: 'ride_rate',
        methods: ['POST']
    )]
    public function rateRide(
        Request $request,
        #[CurrentUser] ?User $user
    ): JsonResponse {
        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $data = json_decode(
                $request->getContent(),
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            if (!is_array($data)) {
                throw new \InvalidArgumentException(
                    'Invalid request body.'
                );
            }

            $rating =
                $this->rideService->rateRide(
                    $user,
                    $data
                );

            return $this->json([
                'success' => true,
                'message' =>
                    'Ride rated successfully.',

                'rating' => [
                    'id' =>
                        $rating->getId(),

                    'rideId' =>
                        $rating->getRide()->getId(),

                    'raterId' =>
                        $rating->getRater()->getId(),

                    'rateeId' =>
                        $rating->getRatee()->getId(),

                    'score' =>
                        $rating->getScore(),

                    'comment' =>
                        $rating->getComment(),

                    'createdAt' =>
                        $rating
                            ->getCreatedAt()
                            ->format(DATE_ATOM),
                ],
            ], Response::HTTP_CREATED);
        } catch (\JsonException) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid JSON request.',
            ], Response::HTTP_BAD_REQUEST);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $e) {
            error_log(sprintf(
                '[RIDE RATE] %s in %s:%d',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ));

            return $this->json([
                'success' => false,
                'message' =>
                    'Unable to rate ride at this time.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}