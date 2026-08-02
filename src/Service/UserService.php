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



        /**
         * Create OTP
         */
        $this->createOTP($user);



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



        if (!$user->isVerified()) {

            throw new \RuntimeException(
                "Please verify your email first"
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







    /**
     * Generate password reset token.
     */
    public function createPasswordResetToken(
        User $user
    ): string {


        $token =
            bin2hex(
                random_bytes(32)
            );


        $user
            ->setResetToken($token)
            ->setResetTokenExpires(
                new \DateTimeImmutable(
                    '+1 hour'
                )
            );



        $this->entityManager->flush();



        return $token;
    }







    /**
     * Reset password.
     */
    public function resetPassword(
        User $user,
        string $password
    ): void {


        $user
            ->setPassword(
                $this->passwordHasher->hashPassword(
                    $user,
                    $password
                )
            )
            ->setResetToken(null)
            ->setResetTokenExpires(null);



        $this->entityManager->flush();
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