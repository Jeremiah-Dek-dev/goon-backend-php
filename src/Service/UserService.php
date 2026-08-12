<?php

namespace App\Service;

use App\Entity\OTP;
use App\Entity\User;
use App\Entity\UserRoleAssignment;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserService
{
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOCK_DURATION_MINUTES = 30;


    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly RefreshTokenService $refreshTokenService,
    ) {
    }



    /**
     * Register new user.
     *
     * Node equivalent:
     *
     * prisma.user.create()
     * prisma.userRoleAssignment.create()
     * prisma.oTP.create()
     */
    public function register(
        string $name,
        string $email,
        string $password,
        ?string $googleId = null,
        ?string $fcmToken = null
    ): User {


        if ($this->userRepository->findOneBy([
            'email' => $email
        ])) {

            throw new \RuntimeException(
                "User already exists"
            );
        }



        $user = new User();

        $user
            ->setName($name)
            ->setEmail($email)
            ->setPassword(
                $this->passwordHasher->hashPassword(
                    $user,
                    $password
                )
            )
            ->setGoogleId($googleId)
            ->setFcmToken($fcmToken)
            ->setVerified(false);



        $this->entityManager->persist($user);



        /**
         * Default USER role
         */
        $role = new UserRoleAssignment();

        $role
            ->setUser($user)
            ->setRole(UserRole::USER);



        $this->entityManager->persist($role);



        $this->entityManager->flush();



        return $user;
    }






    /**
     * Login user.
     *
     * Returns user after validation.
     */
    public function login(
        string $email,
        string $password
    ): User {


        $user = $this->userRepository->findOneBy([
            'email'=>$email
        ]);



        if (!$user) {

            throw new \RuntimeException(
                "Account not found"
            );
        }



        if (!$user->getPassword()) {

            throw new \RuntimeException(
                "Use Google login for this account"
            );
        }




        /**
         * Account lock check
         */
        if (
            $user->getLockUntil()
            &&
            $user->getLockUntil() > new \DateTimeImmutable()
        ) {

            throw new \RuntimeException(
                "Account temporarily locked"
            );
        }





        $valid =
            $this->passwordHasher->isPasswordValid(
                $user,
                $password
            );



        if (!$valid) {


            $attempts =
                $user->getFailedLoginAttempts() + 1;



            $user->setFailedLoginAttempts(
                $attempts
            );



            if ($attempts >= self::MAX_LOGIN_ATTEMPTS) {


                $user->setLockUntil(
                    new \DateTimeImmutable(
                        '+' .
                        self::LOCK_DURATION_MINUTES .
                        ' minutes'
                    )
                );
            }



            $this->entityManager->flush();



            throw new \RuntimeException(
                "Invalid credentials"
            );
        }





        /**
         * Successful login reset
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



        $this->entityManager->flush();



        return $user;
    }






    /**
     * Verify OTP.
     */
    public function verifyOTP(
        User $user,
        string $otp
    ): bool {


        foreach ($user->getOtps() as $record) {


            if (
                $record->getOtp() === $otp
                &&
                $record->getExpiresAt()
                    > new \DateTimeImmutable()
            ) {


                $user->setVerified(true);



                foreach ($user->getOtps() as $oldOtp) {

                    $this->entityManager->remove(
                        $oldOtp
                    );
                }



                $this->entityManager->flush();



                return true;
            }
        }



        return false;
    }







    /**
     * Create OTP record.
     */
    private function createOTP(User $user): OTP
    {

        $otp = new OTP();


        $otp
            ->setUser($user)
            ->setOtp(
                (string) random_int(
                    100000,
                    999999
                )
            )
            ->setExpiresAt(
                new \DateTimeImmutable(
                    '+10 minutes'
                )
            );



        $this->entityManager->persist($otp);


        return $otp;
    }





public function requestPasswordReset(string $email): array
{
    $email = strtolower(trim($email));

    $user = $this->userRepository->findOneBy([
        'email' => $email,
    ]);

    /*
     * Do not reveal whether the account exists.
     */
    if (!$user) {
        return [
            'sent' => false,
        ];
    }

    /*
     * Make sure this is an eligible user/admin account.
     */
    $allowedRoles = [
        UserRole::USER,
        UserRole::ADMIN,
    ];

    $isEligible = false;

    foreach ($user->getRoleAssignments() as $assignment) {
        if (in_array(
            $assignment->getRole(),
            $allowedRoles,
            true
        )) {
            $isEligible = true;
            break;
        }
    }

    if (!$isEligible) {
        return [
            'sent' => false,
        ];
    }

    /*
     * Generate cryptographically secure reset token.
     */
    $plainToken = bin2hex(
        random_bytes(32)
    );

    /*
     * Store only the hash.
     */
    $hashedToken = hash(
        'sha256',
        $plainToken
    );

    /*
     * Token valid for one hour.
     */
    $expiresAt = new \DateTimeImmutable(
        '+1 hour'
    );

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
                UserRole::USER->value,
                UserRole::SUPER_ADMIN->value,
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
     * Google OAuth user creation.
     */
    public function createGoogleUser(
        string $name,
        string $email,
        ?string $avatar,
        string $googleId
    ): User {


        $existing =
            $this->userRepository->findOneBy([
                'email'=>$email
            ]);



        if ($existing) {


        if (!$existing->getGoogleId()) {

            $existing->setGoogleId($googleId);
        }


        if (!$existing->getAvatar()) {

            $existing->setAvatar($avatar);
        }


        $existing->setVerified(true);


        $this->entityManager->flush();


        return $existing;
    }




        $user = new User();


        $user
            ->setName($name)
            ->setEmail($email)
            ->setAvatar($avatar)
            ->setGoogleId($googleId)
            ->setVerified(true);



        $this->entityManager->persist($user);



        $role = new UserRoleAssignment();

        $role
            ->setUser($user)
            ->setRole(UserRole::USER);



        $this->entityManager->persist($role);


        $this->entityManager->flush();



        return $user;
    }




    public function findById(int $id): User
{
    $user = $this->userRepository->find($id);

    if (!$user) {
        throw new \RuntimeException("User not found");
    }

    return $user;
}


public function findByEmail(string $email): User
{
    $user = $this->userRepository->findOneBy([
        'email'=>$email
    ]);

    if (!$user) {
        throw new \RuntimeException("User not found");
    }

    return $user;
}


public function findByResetToken(string $token): User
{
    $user = $this->userRepository->findOneBy([
        'resetToken'=>$token
    ]);

    if (!$user) {
        throw new \RuntimeException("Invalid token");
    }

    return $user;
}
}