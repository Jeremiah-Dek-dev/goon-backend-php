<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\DriverService;
use App\Service\TokenService;
use App\Service\CookieService;
use App\Service\RefreshTokenService;
use App\Service\RideService;
use App\Service\ActivityLogService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\HttpFoundation\Cookie;
use App\Repository\UserRepository;
use App\Repository\CommissionConfigRepository;
use App\Service\EmailService;

#[Route('/api/driver')]
class DriverController extends AbstractController
{

    private const COOKIE_DOMAIN = null;

    public function __construct(

        private readonly DriverService $driverService,

        private readonly TokenService $tokenService,

        private readonly RefreshTokenService $refreshTokenService,

        private readonly CookieService $cookieService,

        private readonly UserRepository $userRepository,

        private readonly EmailService $emailService,

        private readonly RideService $rideService,

        private readonly CommissionConfigRepository $commissionConfigRepository,

        private readonly ActivityLogService $activityLogService,
    ) {}






    /**
     * DRIVER REGISTER
     *
     * Node:
     * registerDriver()
     */
    #[Route('/register', name:'driver_register', methods:['POST'])]
    public function register(
        Request $request
    ): JsonResponse {


        try {


            $data = $request->request->all();
            $avatar = $request->files->get('avatar');



            $result =
                $this->driverService
                ->register(

                    $data['name'],

                    $data['email'],

                    $data['password'],

                    $data['phone'],

                    $data['licenseNumber'],

                    $data['vehicleType'],

                    $data['model'],

                    $data['registrationNumber'],

                    (int)$data['capacity'],

                    $avatar

                );





        $response = $this->json([

            'success'=>true,

            'message'=>$result['message'],

            'driver'=>[

                'id'=>$result['driver']->getId(),

                'userId'=>$result['user']->getId(),

                'name'=>$result['user']->getName(),

                'email'=>$result['user']->getEmail(),

                'phone'=>$result['driver']->getPhone()

            ]

        ], Response::HTTP_CREATED);



        $response->headers->setCookie(
            Cookie::create('driverRegisteredEmail')
                ->withValue($result['user']->getEmail())
                ->withExpires(new \DateTime('+10 minutes'))
                ->withPath('/')
                ->withDomain(self::COOKIE_DOMAIN)
                ->withSecure(true)
                ->withHttpOnly(false)
                ->withSameSite(Cookie::SAMESITE_NONE)
            );

        $this->emailService->sendWelcome($result['user']);

        return $response;


        }catch(\Throwable $e){



            return $this->json([

                'success'=>false,

                'message'=>$e->getMessage()

            ],400);
        }


    }









    /**
     * DRIVER LOGIN
     *
     * Node:
     * loginDriver()
     */
    #[Route('/login', name:'driver_login', methods:['POST'])]
    public function login(
        Request $request
    ): JsonResponse {


        try {


            $data =
                json_decode(
                    $request->getContent(),
                    true
                );

            $user =
                $this->driverService
                ->login(

                    $data['email'],

                    $data['password']
                );


            /**
             * Generate tokens
             */
            $accessToken =

                $this->tokenService
                ->signAccessToken($user);

            $refreshToken =

                $this->refreshTokenService
                ->create($user);


            $response =
                $this->json([


                    'success'=>true,


                    'message'=>
                    'Driver login successful',


                    'roles'=>
                    $user->getRoles(),


                    'driverId'=>
                    $user->getDriverProfile()
                    ->getId(),


                    'userId'=>
                    $user->getId()


                ]);

                
            /**
             * Driver-specific cookies
             */
            $this->cookieService
                ->setDriverAuthCookies(

                    $response,

                    $accessToken,

                    $refreshToken

                );


                $this->emailService->sendWelcome($user);



            return $response;


        }catch(\Throwable $e){

            return $this->json([

                'success'=>false,

                'message'=>$e->getMessage()

            ],401);


        }


    }





    /**
     * CURRENT DRIVER
     *
     * Node:
     * get driver profile
     */
    #[Route('/me', name:'driver_me', methods:['GET'])]
    public function me(

        #[CurrentUser] ?User $user

    ): JsonResponse {

        if(!$user){


            return $this->json([

                'success'=>false,

                'message'=>'Unauthorized'

            ],401);


        }

        $driver =
            $user->getDriverProfile();

        return $this->json([

            'success'=>true,


            'driver'=>[

                'userId'=>$user->getId(),

                'driverId'=>$driver?->getId(),

                'name'=>$user->getName(),

                'email'=>$user->getEmail(),

                'phone'=>$driver?->getPhone(),

                'status'=>$driver?->getStatus()
                    ->value,

                'approved'=>$driver?->isApproved()

            ]

        ]);

    }





    /**
     * REFRESH TOKEN
     */
    #[Route('/refresh-token', name:'driver_refresh_token', methods:['POST'])]
    public function refreshToken(
        Request $request
    ): JsonResponse {

        $rawToken =

            $request
            ->cookies
            ->get(
                'driverRefreshToken'
            );


        if(!$rawToken){


            return $this->json([

                'success'=>false,

                'message'=>'Refresh token missing'

            ],401);


        }



        $storedToken =

            $this->refreshTokenService
            ->validate(
                $rawToken
            );

        if(!$storedToken){


            return $this->json([

                'success'=>false,

                'message'=>'Invalid refresh token'

            ],401);


        }


        $user =
            $storedToken
            ->getUser();

        $newRefreshToken =

            $this->refreshTokenService
            ->rotate(
                $storedToken
            );

        $newAccessToken =

            $this->tokenService
            ->signAccessToken(
                $user
            );

        $response =
            $this->json([

                'success'=>true,

                'message'=>'Token refreshed'

            ]);


        $this->cookieService
            ->setDriverAuthCookies(

                $response,

                $newAccessToken,

                $newRefreshToken

            );

        return $response;


    }









    /**
     * LOGOUT
     */
    #[Route('/logout', name:'driver_logout', methods:['POST'])]
    public function logout(): JsonResponse
    {


        $response =
            new JsonResponse([

                'success'=>true,

                'message'=>
                'Driver logged out successfully'

            ]);


        $this->cookieService
            ->clearDriverAuthCookies(
                $response
            );

        return $response;


    }





    #[Route('/form-submitted', name: 'driver_form_submitted', methods:['GET'])]
    public function formSubmitted(
        Request $request
    ): JsonResponse {

        try {

            // Get registration cookie
            $driverRegisteredEmail = $request
                ->cookies
                ->get('driverRegisteredEmail');

            if ($driverRegisteredEmail === null || $driverRegisteredEmail === '') {
                return $this->json([
                    'success' => false,
                    'message' => 'No recent driver registration found. Please register first.'
                ], 400);
            }

            $driverRegisteredEmail = trim($driverRegisteredEmail);



            // Find user
            $user =
                $this->userRepository
                ->findOneBy([
                    'email' => $driverRegisteredEmail
                ]);



            if (!$user) {


                $response = $this->json([
                    'success' => false,
                    'message' =>
                        'Registration record not found. Please register again.'
                ], 404);


                // clear stale cookie
                $response->headers->clearCookie(
                    'driverRegisteredEmail',
                    '/'
                );


                return $response;

            }



            return $this->json([

                'success'=>true,

                'message'=>
                    'Form submitted successfully. Awaiting admin approval.',

                'data'=>[

                    'email'=>$driverRegisteredEmail,

                    'verified'=>$user->isVerified()

                ]

            ]);



        } catch (\Throwable $e) {

            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ], 500);

        }

    }



/**
 * ADD DRIVER RIDE
 *
 * Creates a new ride for the authenticated driver.
 *e
 */
#[Route('/add', name: 'driver_add_ride', methods: ['POST'])]
public function addRide(
    Request $request,
    #[CurrentUser] ?User $driver
): JsonResponse {
    /*
     * ---------------------------------------------------------
     * 1. Authentication
     * ---------------------------------------------------------
     */
    if (!$driver) {
        return $this->json([
            'success' => false,
            'message' => 'Unauthorized.',
        ], Response::HTTP_UNAUTHORIZED);
    }

    /*
     * ---------------------------------------------------------
     * 2. Extract multipart/form-data
     * ---------------------------------------------------------
     */
    $data = $request->request->all();

        if (isset($data['pickup']) && is_string($data['pickup'])) {
            $data['pickup'] = json_decode(
                $data['pickup'],
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        }

        if (isset($data['destination']) && is_string($data['destination'])) {
            $data['destination'] = json_decode(
                $data['destination'],
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        }

    $image = $request->files->get('image');

    /*
     * ---------------------------------------------------------
     * 3. Normalize nested location fields
     *
     * Supports:
     *
     * pickup[address]
     *
     * destination[address]
     *
     * ---------------------------------------------------------
     */
    $pickup = $data['pickup'] ?? null;

    $destination = $data['destination'] ?? null;

    /*
     * If frontend sends JSON strings inside FormData,
     * decode them as well.
     */
    if (is_string($pickup)) {
        $decodedPickup = json_decode(
            $pickup,
            true
        );

        if (is_array($decodedPickup)) {
            $pickup = $decodedPickup;
        }
    }

    if (is_string($destination)) {
        $decodedDestination = json_decode(
            $destination,
            true
        );

        if (is_array($decodedDestination)) {
            $destination = $decodedDestination;
        }
    }

    $data['pickup'] = $pickup;
    $data['destination'] = $destination;

    /*
     * ---------------------------------------------------------
     * 4. Create ride through service
     * ---------------------------------------------------------
     */
    try {
        $ride = $this->rideService->addRide(
            $driver,
            $data,
            $image
        );

        /*
         * -----------------------------------------------------
         * 5. Activity log
         * -----------------------------------------------------
         */
        try {
            $this->activityLogService->log(
                user: $driver,
                action: 'DRIVER_RIDE_CREATED',
                description: sprintf(
                    'Driver %s created ride #%d from %s to %s.',
                    $driver->getName(),
                    $ride->getId(),
                    $ride->getPickupNorm(),
                    $ride->getDestinationNorm()
                )
            );
        } catch (\Throwable $logException) {
            /*
             * Logging must never cause a successfully-created
             * ride to become a failed request.
             */
        }

        /*
         * -----------------------------------------------------
         * 6. Response
         * -----------------------------------------------------
         */
        return $this->json([
            'success' => true,
            'message' => 'Ride added successfully.',

            'ride' => [
                'id' =>
                    $ride->getId(),

                'pickup' =>
                    $ride->getPickup(),

                'destination' =>
                    $ride->getDestination(),

                'price' =>
                    $ride->getPrice(),

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
                    $ride
                        ->getStatus()
                        ->value,

                'distance' =>
                    $ride->getDistance(),

                'duration' =>
                    $ride->getDuration(),

                'commissionRate' =>
                    $ride->getCommissionRate(),

                'commissionAmount' =>
                    $ride->getCommissionAmount(),

                'payoutAmount' =>
                    $ride->getPayoutAmount(),

                'driver' => [
                    'id' =>
                        $driver->getId(),

                    'name' =>
                        $driver->getName(),

                    'email' =>
                        $driver->getEmail(),

                    'avatar' =>
                        $driver->getAvatar(),
                ],

                'createdAt' =>
                    $ride
                        ->getCreatedAt()
                        ->format(DATE_ATOM),

                'updatedAt' =>
                    $ride
                        ->getUpdatedAt()
                        ->format(DATE_ATOM),
            ],
        ], Response::HTTP_CREATED);

    } catch (\InvalidArgumentException $e) {

        /*
         * Client supplied invalid data.
         */
        return $this->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], Response::HTTP_BAD_REQUEST);

    } catch (\RuntimeException $e) {

        /*
         * Business/service failure.
         */
        return $this->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], Response::HTTP_UNPROCESSABLE_ENTITY);

    } catch (\Throwable $e) {

        /*
         * Unexpected server failure.
         *
         * Never expose the internal exception to the client.
         */
        error_log(sprintf(
            '[DRIVER ADD RIDE] %s in %s:%d',
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        ));

        try {
            $this->activityLogService->log(
                user: $driver,
                action: 'DRIVER_RIDE_CREATION_FAILED',
                description: sprintf(
                    'Failed to create ride. Error: %s',
                    $e->getMessage()
                )
            );
        } catch (\Throwable $logException) {
            // Never mask the original exception.
        }

        return $this->json([
            'success' => false,
            'message' =>
                'Unable to create ride at this time.',
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}





#[Route('/commission-rate', name: 'driver_commission_rate', methods: ['GET'])]
public function commissionRate(): JsonResponse
{
    try {
        $config = $this->commissionConfigRepository->findActive();

        if (!$config) {
            return $this->json([
                'success' => true,
                'rate' => 0.0,
                'message' => 'No active commission configuration found.'
            ]);
        }

        return $this->json([
            'success' => true,
            'rate' => $config->getRate(),
        ]);

    } catch (\Throwable $e) {
        return $this->json([
            'success' => false,
            'message' => 'Unable to retrieve commission rate.'
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}




/**
 * GET DRIVER RIDES
 *
 * Returns rides created by the authenticated driver.
 */
#[Route('/rides', name: 'driver_rides', methods: ['GET'])]
public function rides(
    #[CurrentUser] ?User $driver
): JsonResponse {
    if (!$driver) {
        return $this->json([
            'success' => false,
            'message' => 'Unauthorized.',
        ], Response::HTTP_UNAUTHORIZED);
    }

    try {
        $rides =
            $this->rideService
                ->getDriverRides($driver);

        $formattedRides = [];

        foreach ($rides as $ride) {
            $formattedRides[] = [
                'id' =>
                    $ride->getId(),

                'pickup' =>
                    $ride->getPickup(),

                'destination' =>
                    $ride->getDestination(),

                'price' =>
                    $ride->getPrice(),

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
                    $ride
                        ->getStatus()
                        ->value,

                'distance' =>
                    $ride->getDistance(),

                'duration' =>
                    $ride->getDuration(),

                'pickupNorm' =>
                    $ride->getPickupNorm(),

                'destinationNorm' =>
                    $ride->getDestinationNorm(),

                'driver' => [
                    'id' =>
                        $driver->getId(),

                    'name' =>
                        $driver->getName(),

                    'email' =>
                        $driver->getEmail(),

                    'avatar' =>
                        $driver->getAvatar(),
                ],

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

        return $this->json([
            'success' => true,
            'rides' => $formattedRides,
            'total' => count($formattedRides),
        ]);

    } catch (\Throwable $e) {

        error_log(sprintf(
            '[DRIVER RIDES] %s in %s:%d',
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        ));

        return $this->json([
            'success' => false,
            'message' => 'Unable to retrieve your rides.',
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}




/**
 * GET DRIVER RIDE DETAILS
 */
#[Route('/rides/{id}', name: 'driver_ride_details', methods: ['GET'], requirements: ['id' => '\d+']
)]
public function rideDetails(
    int $id,
    #[CurrentUser] ?User $driver
): JsonResponse {
    if (!$driver) {
        return $this->json([
            'success' => false,
            'message' => 'Unauthorized.',
        ], Response::HTTP_UNAUTHORIZED);
    }

    try {
        $ride =
            $this->rideService
                ->getDriverRide(
                    $driver,
                    $id
                );

        return $this->json([
            'success' => true,

            'ride' => [
                'id' =>
                    $ride->getId(),

                'pickup' =>
                    $ride->getPickup(),

                'destination' =>
                    $ride->getDestination(),

                'price' =>
                    $ride->getPrice(),

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
                    $ride
                        ->getStatus()
                        ->value,

                'distance' =>
                    $ride->getDistance(),

                'duration' =>
                    $ride->getDuration(),

                'pickupNorm' =>
                    $ride->getPickupNorm(),

                'destinationNorm' =>
                    $ride->getDestinationNorm(),

                'driver' => [
                    'id' =>
                        $driver->getId(),

                    'name' =>
                        $driver->getName(),

                    'email' =>
                        $driver->getEmail(),

                    'avatar' =>
                        $driver->getAvatar(),
                ],

                'createdAt' =>
                    $ride
                        ->getCreatedAt()
                        ->format(DATE_ATOM),

                'updatedAt' =>
                    $ride
                        ->getUpdatedAt()
                        ->format(DATE_ATOM),
            ],
        ]);

    } catch (\RuntimeException $e) {

        return $this->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], Response::HTTP_NOT_FOUND);

    } catch (\Throwable $e) {

        error_log(sprintf(
            '[DRIVER RIDE DETAILS] %s in %s:%d',
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        ));

        return $this->json([
            'success' => false,
            'message' => 'Unable to retrieve ride.',
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}



/**
 * DELETE DRIVER RIDE
 */
#[Route('/rides/{id}', name: 'driver_delete_ride', methods: ['DELETE'], requirements: ['id' => '\d+'])]
public function deleteRide(
    int $id,
    #[CurrentUser] ?User $driver
): JsonResponse {
    if (!$driver) {
        return $this->json([
            'success' => false,
            'message' => 'Unauthorized.',
        ], Response::HTTP_UNAUTHORIZED);
    }

    try {

        $this->rideService
            ->deleteDriverRide(
                $driver,
                $id
            );

        try {
            $this->activityLogService->log(
                user: $driver,
                action: 'DRIVER_RIDE_DELETED',
                description: sprintf(
                    'Driver %s deleted ride #%d.',
                    $driver->getName(),
                    $id
                )
            );
        } catch (\Throwable $logException) {
            // Logging must never break deletion.
        }

        return $this->json([
            'success' => true,
            'message' => 'Ride deleted successfully.',
            'rideId' => $id,
        ]);

    } catch (\RuntimeException $e) {

        return $this->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], Response::HTTP_UNPROCESSABLE_ENTITY);

    } catch (\Throwable $e) {

        error_log(sprintf(
            '[DRIVER DELETE RIDE] %s in %s:%d',
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        ));

        return $this->json([
            'success' => false,
            'message' => 'Unable to delete ride.',
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
}