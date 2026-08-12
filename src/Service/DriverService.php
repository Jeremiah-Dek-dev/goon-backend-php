<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\DriverProfile;
use App\Entity\UserRoleAssignment;
use App\Enum\UserRole;
use App\Enum\DriverStatus;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Enum\VehicleType;

class DriverService
{

    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOCK_DURATION_MINUTES = 30;


    public function __construct(

        private readonly EntityManagerInterface $entityManager,

        private readonly UserRepository $userRepository,

        private readonly UserPasswordHasherInterface $passwordHasher

    ) {}


    /**
     * Register Driver
     *
     * Node equivalent:
     *
     * registerDriver()
     */
    public function register(

        string $name,

        string $email,

        string $password,

        string $phone,

        string $licenseNumber,

        string $vehicleType,

        string $model,

        string $registrationNumber,

        int $capacity,

        ?string $avatar = null

    ): array {


        return $this->entityManager
            ->wrapInTransaction(function() use (

                $name,
                $email,
                $password,
                $phone,
                $licenseNumber,
                $vehicleType,
                $model,
                $registrationNumber,
                $capacity,
                $avatar

            ){

                /**
                 * Find existing account
                 */
                $user =
                    $this->userRepository
                    ->findOneBy([
                        'email'=>$email
                    ]);


                /**
                 * EXISTING USER
                 *
                 * Add driver role
                 */
                if($user){


                    foreach($user->getRoleAssignments() as $role){


                        if(
                            $role->getRole()
                            ===
                            UserRole::DRIVER
                        ){

                            throw new \RuntimeException(
                                "A driver account with this email already exists."
                            );
                        }

                    }


                    /**
                     * Set password if missing
                     */
                    if(!$user->getPassword()){


                        $user->setPassword(

                            $this->passwordHasher
                            ->hashPassword(
                                $user,
                                $password
                            )

                        );

                    }


                    /**
                     * Add driver role
                     */
                    $role =
                        new UserRoleAssignment();


                    $role
                        ->setUser($user)
                        ->setRole(UserRole::DRIVER);



                    $this->entityManager
                        ->persist($role);

                    /**
                     * Create Driver Profile
                     */
                    $driver =
                        new DriverProfile();


                    try {

                    $vehicleTypeEnum = VehicleType::from($vehicleType);

                } catch(\ValueError $e) {

                    throw new \RuntimeException(
                        "Invalid vehicle type."
                    );

                }


                    $driver
                        ->setUser($user)
                        ->setPhone($phone)
                        ->setLicenseNumber($licenseNumber)
                        ->setVehicleType($vehicleTypeEnum)
                        ->setModel($model)
                        ->setRegistrationNumber($registrationNumber)
                        ->setCapacity($capacity)
                        ->setStatus(DriverStatus::PENDING)
                        ->setApproved(false);




                    $this->entityManager
                        ->persist($driver);



                    $this->entityManager
                        ->flush();



                    return [

                        'user'=>$user,

                        'driver'=>$driver,

                        'message'=>
                        'Driver role added. Awaiting approval.'

                    ];

                }







                /**
                 * NEW USER
                 */

                $user =
                    new User();



                $user
                    ->setName($name)
                    ->setEmail($email)
                    ->setAvatar($avatar)
                    ->setVerified(true);



                $user->setPassword(

                    $this->passwordHasher
                    ->hashPassword(
                        $user,
                        $password
                    )

                );




                $this->entityManager
                    ->persist($user);





                /**
                 * Driver role
                 */
                $role =
                    new UserRoleAssignment();


                $role
                    ->setUser($user)
                    ->setRole(UserRole::DRIVER);



                $this->entityManager
                    ->persist($role);


                /**
                 * Driver profile
                 */
                $driver =
                    new DriverProfile();

                try {

                    $vehicleTypeEnum = VehicleType::from($vehicleType);

                } catch(\ValueError $e) {

                    throw new \RuntimeException(
                        "Invalid vehicle type."
                    );

                }

                $driver
                    ->setUser($user)
                    ->setPhone($phone)
                    ->setLicenseNumber($licenseNumber)
                    ->setVehicleType($vehicleTypeEnum)
                    ->setModel($model)
                    ->setRegistrationNumber($registrationNumber)
                    ->setCapacity($capacity)
                    ->setStatus(DriverStatus::PENDING)
                    ->setApproved(false);


                $this->entityManager
                    ->persist($driver);


                $this->entityManager
                    ->flush();


                return [

                    'user'=>$user,

                    'driver'=>$driver,

                    'message'=>
                    'Driver registration successful. Awaiting approval.'

                ];

            });


    }









    /**
     * Driver Login
     *
     * Node equivalent:
     *
     * loginDriver()
     */
    public function login(

        string $email,

        string $password

    ): User {

        $user =
            $this->userRepository
            ->findOneBy([
                'email'=>$email
            ]);

        if(!$user){

            throw new \RuntimeException(
                "Invalid email or password."
            );

        }

        /**
         * Check DRIVER role
         */
        $isDriver=false;


        foreach($user->getRoleAssignments() as $role){


            if(
                $role->getRole()
                ===
                UserRole::DRIVER
            ){

                $isDriver=true;
                break;

            }

        }

        if(!$isDriver){

            throw new \RuntimeException(
                "Not authorized as driver."
            );

        }


        $driver =
            $user->getDriverProfile();


        if(!$driver){

            throw new \RuntimeException(
                "Driver profile not found."
            );

        }

       /**
         * Approval check
         */
        if(!$driver->isApproved()){

            throw new \RuntimeException(
                "Your account is pending admin approval."
            );

        }

        if(
            $driver->getStatus()
            !==
            DriverStatus::ACTIVE
        ){

            throw new \RuntimeException(
                "Driver account is not active."
            );

        }

        /**
         * Account lock
         */
        if(

            $user->getLockUntil()

            &&

            $user->getLockUntil()
            >
            new \DateTimeImmutable()

        ){

            throw new \RuntimeException(
                "Account temporarily locked."
            );

        }

        if(!$user->getPassword()){


            throw new \RuntimeException(
                "Password not set."
            );

        }


        /**
         * Password check
         */
        $valid =

            $this->passwordHasher
            ->isPasswordValid(
                $user,
                $password
            );

        if(!$valid){

            $attempts =
                $user->getFailedLoginAttempts()+1;


            $user
                ->setFailedLoginAttempts(
                    $attempts
                );


            if(
                $attempts >= self::MAX_LOGIN_ATTEMPTS
            ){


                $user->setLockUntil(

                    new \DateTimeImmutable(
                        '+' .
                        self::LOCK_DURATION_MINUTES .
                        ' minutes'
                    )

                );

            }

            $this->entityManager
                ->flush();

            throw new \RuntimeException(
                "Incorrect email or password."
            );

        }

        /**
         * Successful login
         */

        $user
            ->setFailedLoginAttempts(0)
            ->setLockUntil(null)
            ->setLastLoginAt(
                new \DateTimeImmutable()
            )
            ->setLastActiveAt(
                new \DateTimeImmutable()
            );

        $this->entityManager
            ->flush();

        return $user;

    }





}