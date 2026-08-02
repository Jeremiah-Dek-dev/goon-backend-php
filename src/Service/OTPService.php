<?php

namespace App\Service;

use App\Entity\OTP;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class OTPService
{

    private const OTP_EXPIRATION_MINUTES = 10;


    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }




    /**
     * Create and save OTP.
     *
     * Node equivalent:
     *
     * generateOTP()
     * prisma.oTP.create()
     */
    public function generate(User $user): string
    {

        $otp = (string) random_int(
            100000,
            999999
        );


        $record = new OTP();


        $record
            ->setUser($user)
            ->setOtp($otp)
            ->setExpiresAt(
                new \DateTimeImmutable(
                    '+' .
                    self::OTP_EXPIRATION_MINUTES .
                    ' minutes'
                )
            );


        $this->entityManager->persist($record);

        $this->entityManager->flush();


        return $otp;
    }







    /**
     * Validate OTP.
     *
     * Node equivalent:
     *
     * prisma.OTP.findFirst({
     * userId,
     * otp,
     * expiresAt:{gte:new Date()}
     * })
     */
    public function verify(
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


                $this->removeUserOtps($user);


                $this->entityManager->flush();


                return true;
            }
        }


        return false;
    }








    /**
     * Resend OTP.
     */
    public function resend(User $user): string
    {

        // Remove old OTPs first
        $this->removeUserOtps($user);


        return $this->generate($user);
    }







    /**
     * Delete all OTP records.
     */
    private function removeUserOtps(User $user): void
    {

        foreach ($user->getOtps() as $otp) {

            $this->entityManager->remove($otp);

        }
    }








    /**
     * Cleanup expired OTPs.
     * 
     * Later this can run as Symfony Messenger scheduled job.
     */
    public function removeExpired(): void
    {

        $expired =
            $this->entityManager
                ->getRepository(OTP::class)
                ->createQueryBuilder('otp')
                ->where(
                    'otp.expiresAt < :now'
                )
                ->setParameter(
                    'now',
                    new \DateTimeImmutable()
                )
                ->getQuery()
                ->getResult();



        foreach ($expired as $otp) {

            $this->entityManager->remove($otp);

        }


        $this->entityManager->flush();
    }

}