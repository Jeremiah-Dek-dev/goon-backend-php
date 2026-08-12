<?php

namespace App\Service;

use App\Entity\AdminProfile;
use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use App\Repository\BookingRepository;
use App\Entity\Booking;
use App\Entity\CommissionConfig;
use App\Repository\CommissionConfigRepository;
use App\Repository\RideRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\String\ByteString;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class AdminService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly BookingRepository $bookingRepository,
        private readonly CommissionConfigRepository $commissionConfigRepository,
        private readonly RideRepository $rideRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        
        #[Autowire('%system_admin.email%')]
        private readonly string $systemAdminEmail,
        #[Autowire('%system_admin.password%')]
        private readonly string $systemAdminPassword,
        #[Autowire('%system_admin.name%')]
        private readonly string $systemAdminName
    ) {
    }


    /**
     * Create default super admin if none exists.
     *
     * This should normally run during application boot,
     * deployment, or database initialization.
     */
    public function ensureSuperAdminExists(): void
    {
        $existingSuperAdmin = $this->userRepository
            ->findOneByRole(UserRole::SUPER_ADMIN);


        if ($existingSuperAdmin) {
            return;
        }


        $this->createAdmin([
            'name' => $this->systemAdminName,
            'email' => $this->systemAdminEmail,
            'password' => $this->systemAdminPassword,
            'role' => UserRole::SUPER_ADMIN,
        ]);
    }



    /**
     * Create an admin account.
     */
    public function createAdmin(array $data): User
    {
        $this->entityManager->beginTransaction();


        try {

            /**
             * Prevent duplicate email
             */
            $existingUser = $this->userRepository
                ->findOneBy([
                    'email' => strtolower($data['email'])
                ]);


            if ($existingUser) {
                throw new \RuntimeException(
                    'User with this email already exists.'
                );
            }



            /**
             * Create User entity
             */
            $user = new User();

            $user->setName(
                $data['name']
            );

            $user->setEmail(
                strtolower($data['email'])
            );


            $user->setPassword(
                $this->passwordHasher->hashPassword(
                    $user,
                    $data['password']
                )
            );


            // Admin accounts are verified by default
            $user->setVerified(true);



            /**
             * Assign role
             */
            $user->addRole(
                $data['role'] ?? UserRole::ADMIN
            );



            /**
             * Create admin profile
             */
            $adminProfile = new AdminProfile();

            $adminProfile->setUser($user);



            /**
             * Maintain bidirectional relationship
             */
            // User owns the reference from Doctrine perspective
            // because AdminProfile has JoinColumn,
            // but setting both sides keeps the object graph consistent.

            $this->entityManager->persist($user);

            $this->entityManager->persist($adminProfile);



            $this->entityManager->flush();

            $this->entityManager->commit();


            return $user;


        } catch (\Throwable $exception) {

            $this->entityManager->rollback();

            throw $exception;
        }
    }





    /**
 * Authenticate an administrator.
 *
 * @throws \RuntimeException
 */
public function login(
    string $email,
    string $password
): User {
    $email = strtolower(trim($email));

    $user = $this->userRepository->findOneBy([
        'email' => $email,
    ]);

    if (!$user) {
        throw new \RuntimeException('Account not found');
    }

    /*
     * Only password-based accounts can use admin login.
     */
    if (!$user->getPassword()) {
        throw new \RuntimeException(
            'Use the configured authentication method for this account.'
        );
    }

    /*
     * Ensure the account has an administrative role.
     */
    $adminRoles = [
        'ROLE_ADMIN',
        'ROLE_SUPER_ADMIN',
        'ROLE_ADMIN_MANAGER',
    ];

    $hasAdminRole = false;

    foreach ($user->getRoles() as $role) {
        if (in_array($role, $adminRoles, true)) {
            $hasAdminRole = true;
            break;
        }
    }

    if (!$hasAdminRole) {
        throw new \RuntimeException(
            'Access denied: administrator account required.'
        );
    }

    /*
     * Check whether the account is currently locked.
     */
    $lockUntil = $user->getLockUntil();

    if (
        $lockUntil !== null &&
        $lockUntil > new \DateTimeImmutable()
    ) {
        throw new \RuntimeException(
            'Account temporarily locked'
        );
    }

    /*
     * Verify password.
     */
    $valid = $this->passwordHasher->isPasswordValid(
        $user,
        $password
    );

    if (!$valid) {
        $attempts = $user->getFailedLoginAttempts() + 1;

        $user->setFailedLoginAttempts($attempts);

        /*
         * Lock after 5 failed attempts.
         */
        if ($attempts >= 5) {
            $user->setLockUntil(
                new \DateTimeImmutable('+30 minutes')
            );
        }

        $this->entityManager->flush();

        throw new \RuntimeException(
            'Invalid credentials'
        );
    }

    /*
     * Successful login.
     */
    $user
        ->setFailedLoginAttempts(0)
        ->setLockUntil(null)
        ->setLastLoginAt(new \DateTimeImmutable())
        ->setLastActiveAt(new \DateTimeImmutable());

    $this->entityManager->flush();

    return $user;
}






/**
 * Request an administrator password reset.
 *
 * Returns the raw token so the controller can construct the
 * frontend reset URL.
 *
 * @throws \RuntimeException
 */
public function requestPasswordReset(string $email): array
{
    $email = strtolower(trim($email));

    $user = $this->userRepository->findOneBy([
        'email' => $email,
    ]);

    /*
     * Do not expose whether the account exists.
     *
     * The controller can return the same response regardless
     * of whether an admin was found.
     */
    if (!$user) {
        return [
            'sent' => false,
        ];
    }

    /*
     * Make sure this is actually an administrator.
     */
    $adminRoles = [
        UserRole::ADMIN,
        UserRole::SUPER_ADMIN,
        UserRole::ADMIN_MANAGER,
    ];

    $isAdmin = false;

    foreach ($adminRoles as $role) {
        foreach ($user->getRoleAssignments() as $assignment) {
            if ($assignment->getRole() === $role) {
                $isAdmin = true;
                break 2;
            }
        }
    }

    if (!$isAdmin) {
        return [
            'sent' => false,
        ];
    }

    /*
     * Generate a cryptographically secure random token.
     *
     * 32 bytes = 256 bits of entropy.
     */
    $plainToken = bin2hex(random_bytes(32));

    /*
     * Store only the SHA-256 hash in the database.
     */
    $hashedToken = hash('sha256', $plainToken);

    /*
     * Token expires after one hour.
     */
    $expiresAt = new \DateTimeImmutable('+1 hour');

    $user
        ->setResetToken($hashedToken)
        ->setResetTokenExpires($expiresAt);

    $this->entityManager->flush();

    return [
        'sent' => true,
        'user' => $user,
        'token' => $plainToken,
        'expiresAt' => $expiresAt,
    ];
}




/**
 * Reset an administrator password using a valid reset token.
 *
 * @throws \RuntimeException
 */
public function resetPassword(
    string $token,
    string $newPassword
): User {
    $token = trim($token);

    if ($token === '') {
        throw new \RuntimeException(
            'Invalid or expired reset token.'
        );
    }

    if ($newPassword === '') {
        throw new \RuntimeException(
            'New password is required.'
        );
    }

    /*
     * Validate password strength here according to your
     * application's password policy.
     */
    if (strlen($newPassword) < 8) {
        throw new \RuntimeException(
            'Password must contain at least 8 characters.'
        );
    }

    /*
     * Hash the supplied token exactly the same way it was
     * hashed when the reset request was created.
     */
    $hashedToken = hash(
        'sha256',
        $token
    );

    /*
     * Find the account using the hashed token.
     */
    $user = $this->userRepository
        ->createQueryBuilder('u')
        ->innerJoin('u.roleAssignments', 'roleAssignment')
        ->andWhere('u.resetToken = :token')
        ->andWhere('u.resetTokenExpires > :now')
        ->andWhere('roleAssignment.role IN (:roles)')
        ->setParameter('token', $hashedToken)
        ->setParameter(
            'now',
            new \DateTimeImmutable()
        )
        ->setParameter(
            'roles',
            [
                UserRole::ADMIN->value,
                UserRole::SUPER_ADMIN->value,
                UserRole::ADMIN_MANAGER->value,
            ]
        )
        ->setMaxResults(1)
        ->getQuery()
        ->getOneOrNullResult();

    if (!$user) {
        throw new \RuntimeException(
            'Invalid or expired reset token.'
        );
    }

    /*
     * Hash using Symfony's configured password hasher.
     */
    $hashedPassword = $this->passwordHasher->hashPassword(
        $user,
        $newPassword
    );

    /*
     * Update credentials and invalidate the reset token.
     */
    $user
        ->setPassword($hashedPassword)
        ->setResetToken(null)
        ->setResetTokenExpires(null)

        /*
         * Reset login protection state.
         */
        ->setFailedLoginAttempts(0)
        ->setLockUntil(null);

    $this->entityManager->flush();

    return $user;
}



/**
 * Change the authenticated administrator's password.
 */
public function changePassword(
    User $admin,
    string $newPassword
): void {

    $hashedPassword = $this->passwordHasher->hashPassword(
        $admin,
        $newPassword
    );

    $admin->setPassword($hashedPassword);

    $this->entityManager->persist($admin);
    $this->entityManager->flush();
}



public function generateBackupCodes(AdminProfile $adminProfile): array
{
    $plainCodes = [];
    $entities = [];

    for ($i = 0; $i < 10; $i++) {
        // 10-character hexadecimal recovery code.
        $plainCode = strtoupper(bin2hex(random_bytes(5)));

        $backupCode = new \App\Entity\BackupCode();

        $backupCode
            ->setCode(
                password_hash(
                    $plainCode,
                    PASSWORD_BCRYPT
                )
            )
            ->setUsed(false)
            ->setAdminProfile($adminProfile);

        $plainCodes[] = $plainCode;
        $entities[] = $backupCode;
    }

    return [
        'plainCodes' => $plainCodes,
        'entities' => $entities,
    ];
}


/**
 * Get administrator dashboard statistics.
 */
public function getDashboardStats(): array
{
    $totalBookings = $this->bookingRepository->countAll();

    $totalRevenue = $this->bookingRepository->getTotalRevenue();

    $totalRides = $this->rideRepository->count([]);

    $totalUsers = $this->userRepository->count([]);

    $totalDrivers = $this->userRepository->countByRole(
        UserRole::DRIVER
    );

    return [
        'totalBookings' => $totalBookings,
        'totalRevenue' => $totalRevenue,
        'totalRides' => $totalRides,
        'totalUsers' => $totalUsers,
        'totalDrivers' => $totalDrivers,
    ];
}





/**
 * Get monthly revenue for the current year.
 */
public function getMonthlyRevenue(?int $year = null): array
{
    $year ??= (int) (new \DateTimeImmutable())->format('Y');

    $monthlyRevenue = $this->bookingRepository
        ->getMonthlyRevenue($year);

    $data = [];

    for ($month = 1; $month <= 12; $month++) {
        $data[] = [
            'month' => $month,
            'revenue' => $monthlyRevenue[$month] ?? 0.0,
        ];
    }

    return $data;
}




/**
 * Get booking status distribution.
 */
public function getBookingStatusDistribution(): array
{
    return $this->bookingRepository->getStatusDistribution();
}




/**
 * Get monthly booking counts for the current year.
 */
public function getMonthlyBookings(?int $year = null): array
{
    $year ??= (int) (new \DateTimeImmutable())->format('Y');

    $monthlyBookings = $this->bookingRepository
        ->getMonthlyBookings($year);

    $data = [];

    for ($month = 1; $month <= 12; $month++) {
        $data[] = [
            'month' => $month,
            'bookings' => $monthlyBookings[$month] ?? 0,
        ];
    }

    return $data;
}




/**
 * Get all bookings.
 */
public function getAllBookings(): array
{
    return $this->bookingRepository
        ->createQueryBuilder('b')
        ->leftJoin('b.user', 'u')
        ->addSelect('u')
        ->leftJoin('b.ride', 'r')
        ->addSelect('r')
        ->leftJoin('r.driver', 'd')
        ->addSelect('d')
        ->orderBy('b.createdAt', 'DESC')
        ->getQuery()
        ->getResult();
}


/**
 * Get the currently active commission rate.
 */
public function getCommissionRate(): float
{
    $config = $this->commissionConfigRepository->findActive();

    if (!$config) {
        return 0.0;
    }

    return $config->getRate();
}




/**
 * Set a new platform commission rate.
 *
 * @throws \InvalidArgumentException
 */
public function setCommissionRate(
    float $rate,
    ?\DateTimeImmutable $effectiveFrom = null
): CommissionConfig {
    if ($rate < 0 || $rate > 1) {
        throw new \InvalidArgumentException(
            'Rate must be between 0 and 1'
        );
    }

    $this->entityManager->beginTransaction();

    try {
        /*
         * Deactivate existing active configuration(s).
         */
        $this->commissionConfigRepository->deactivateAll();

        /*
         * Create new active configuration.
         */
        $config = new CommissionConfig();

        $config->setRate($rate);
        $config->setEffectiveFrom(
            $effectiveFrom ?? new \DateTimeImmutable()
        );
        $config->setActive(true);

        $this->entityManager->persist($config);
        $this->entityManager->flush();

        $this->entityManager->commit();

        return $config;

    } catch (\Throwable $e) {

        $this->entityManager->rollback();

        throw $e;
    }
}


/**
 * Get all drivers with their profiles.
 *
 * Returns users having the DRIVER role, ordered newest first.
 *
 * @return User[]
 */
public function getAllDrivers(): array
{
    return $this->userRepository
        ->createQueryBuilder('u')
        ->innerJoin('u.roleAssignments', 'roleAssignment')
        ->leftJoin('u.driverProfile', 'driverProfile')
        ->addSelect('roleAssignment')
        ->addSelect('driverProfile')
        ->andWhere('roleAssignment.role = :role')
        ->setParameter('role', UserRole::DRIVER)
        ->orderBy('u.createdAt', 'DESC')
        ->getQuery()
        ->getResult();
}


/**
 * Approve a driver.
 */
public function approveDriver(int $driverId): \App\Entity\DriverProfile
{
    if ($driverId <= 0) {
        throw new \InvalidArgumentException(
            'Invalid or missing driverId.'
        );
    }

    $driver = $this->userRepository->find($driverId);

    if (!$driver) {
        throw new \RuntimeException(
            'Driver not found.'
        );
    }

    /*
     * Make sure this user actually has DRIVER role.
     */
    $isDriver = in_array(
        'ROLE_DRIVER',
        $driver->getRoles(),
        true
    );

    if (!$isDriver) {
        throw new \RuntimeException(
            'User is not a driver.'
        );
    }

    /*
     * Driver profile must exist.
     */
    $profile = $driver->getDriverProfile();

    if (!$profile) {
        throw new \RuntimeException(
            'Driver profile not found.'
        );
    }

    /*
     * Approve driver and activate account.
     */
    $profile
        ->setApproved(true)
        ->setStatus(\App\Enum\DriverStatus::ACTIVE);

    $this->entityManager->flush();

    return $profile;
}
}