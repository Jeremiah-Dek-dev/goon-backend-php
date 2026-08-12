<?php

namespace App\Service;

use App\Entity\AdminProfile;
use App\Entity\BackupCode;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use OTPHP\TOTP;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class TwoFactorService
{
    private const TOTP_PERIOD = 30;
    private const TOTP_DIGITS = 6;
    private const BACKUP_CODE_COUNT = 10;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    /**
     * Generate the initial TOTP secret.
     */
    public function generateSecret(): string
    {
        $totp = TOTP::create();

        $totp->setPeriod(self::TOTP_PERIOD);
        $totp->setDigits(self::TOTP_DIGITS);
        $totp->setDigest('sha1');

        return $totp->getSecret();
    }

    /**
     * Build the otpauth:// URI used by authenticator apps.
     */
    public function getProvisioningUri(
        string $secret,
        string $email
    ): string {
        $totp = TOTP::createFromSecret($secret);

        $totp->setLabel('GoOn Admin ' . $email);
        $totp->setIssuer('GoOn');
        $totp->setPeriod(self::TOTP_PERIOD);
        $totp->setDigits(self::TOTP_DIGITS);
        $totp->setDigest('sha1');

        return $totp->getProvisioningUri();
    }

    /**
     * Verify a TOTP code.
     */
    public function verifyCode(
        string $secret,
        string $code
    ): bool {
        $totp = TOTP::createFromSecret($secret);

        $totp->setPeriod(self::TOTP_PERIOD);
        $totp->setDigits(self::TOTP_DIGITS);
        $totp->setDigest('sha1');

        /*
         * OTPHP's verification allows time tolerance.
         * We accept the current step plus/minus one step.
         */
        return $totp->verify(
            trim($code),
            null,
            1
        );
    }

    /**
     * Generate backup codes.
     *
     * Returns plaintext codes only for the response.
     * Only hashes are persisted.
     *
     * @return array{
     *     plainCodes: array<int, string>,
     *     codes: array<int, BackupCode>
     * }
     */
    public function generateBackupCodes(
        AdminProfile $adminProfile
    ): array {
        $plainCodes = [];
        $entities = [];

        for ($i = 0; $i < self::BACKUP_CODE_COUNT; $i++) {
            $plainCode = strtoupper(
                bin2hex(random_bytes(5))
            );

            $plainCodes[] = $plainCode;

            $backupCode = new BackupCode();

            $backupCode
                ->setCode(
                    password_hash(
                        $plainCode,
                        PASSWORD_BCRYPT
                    )
                )
                ->setAdminProfile($adminProfile)
                ->setUsed(false);

            $entities[] = $backupCode;
        }

        return [
            'plainCodes' => $plainCodes,
            'codes' => $entities,
        ];
    }

    /**
     * Replace all existing backup codes.
     */
    public function regenerateBackupCodes(
        AdminProfile $adminProfile
    ): array {
        foreach ($adminProfile->getBackupCodes()->toArray() as $oldCode) {
            $this->entityManager->remove($oldCode);
        }

        $this->entityManager->flush();

        $result = $this->generateBackupCodes($adminProfile);

        foreach ($result['codes'] as $code) {
            $this->entityManager->persist($code);
        }

        $adminProfile->setBackupCodesGeneratedAt(
            new \DateTimeImmutable()
        );

        $this->entityManager->flush();

        return $result;
    }

    /**
     * Find and consume a matching unused backup code.
     */
    public function consumeBackupCode(
        AdminProfile $adminProfile,
        string $plainCode
    ): bool {
        foreach ($adminProfile->getBackupCodes() as $backupCode) {
            if ($backupCode->isUsed()) {
                continue;
            }

            if (
                password_verify(
                    trim($plainCode),
                    $backupCode->getCode()
                )
            ) {
                $backupCode->setUsed(true);

                $this->entityManager->flush();

                return true;
            }
        }

        return false;
    }



    public function generateQrDataUrl(
        string $provisioningUri
    ): string {
        $result = (new Builder(
            writer: new PngWriter(),
            data: $provisioningUri,
            size: 300,
            margin: 10,
        ))->build();

        return $result->getDataUri();
    }
}