<?php

namespace App\Service;

use App\Entity\User;
use App\Entity\VerificationSession;
use Doctrine\ORM\EntityManagerInterface;

class VerificationSessionService
{
    private const TTL_MINUTES = 15;
    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function create(User $user): string
    {
        $token = bin2hex(random_bytes(32));

        $session = new VerificationSession();

        $session
            ->setUser($user)
            ->setTokenHash(
                hash('sha256', $token)
            )
            ->setExpiresAt(
                new \DateTimeImmutable(
                    '+' . self::TTL_MINUTES . ' minutes'
                )
            );

        $this->entityManager->persist($session);
        $this->entityManager->flush();

        return $token;
    }

    public function findUserByToken(
        string $token
    ): ?User {
        $session = $this->findSession($token);

        if (!$session) {
            return null;
        }

        return $session->getUser();
    }

    public function findSession(
        string $token
    ): ?VerificationSession {
        if ($token === '') {
            return null;
        }

        $hash = hash('sha256', $token);

        $session = $this->entityManager
            ->getRepository(VerificationSession::class)
            ->findOneBy([
                'tokenHash' => $hash,
            ]);

        if (!$session) {
            return null;
        }

        if (
            $session->getExpiresAt()
            <= new \DateTimeImmutable()
        ) {
            $this->entityManager->remove($session);
            $this->entityManager->flush();

            return null;
        }

        if (
            $session->getAttempts()
            >= self::MAX_ATTEMPTS
        ) {
            $this->entityManager->remove($session);
            $this->entityManager->flush();

            return null;
        }

        return $session;
    }

    public function registerFailedAttempt(
        VerificationSession $session
    ): void {
        $session->increaseAttempts();

        if (
            $session->getAttempts()
            >= self::MAX_ATTEMPTS
        ) {
            $this->entityManager->remove($session);
        }

        $this->entityManager->flush();
    }

    public function consume(string $token): void
    {
        $session = $this->findSession($token);

        if (!$session) {
            return;
        }

        $this->entityManager->remove($session);
        $this->entityManager->flush();
    }

    public function delete(string $token): void
    {
        $this->consume($token);
    }

    public function refresh(User $user): string
    {
        $oldSessions = $this->entityManager
            ->getRepository(VerificationSession::class)
            ->findBy([
                'user' => $user,
            ]);

        foreach ($oldSessions as $session) {
            $this->entityManager->remove($session);
        }

        $this->entityManager->flush();

        return $this->create($user);
    }
}