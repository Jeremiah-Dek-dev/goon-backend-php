<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class VerificationSession
{

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;


    #[ORM\ManyToOne(
        targetEntity: User::class
    )]
    #[ORM\JoinColumn(
        nullable:false,
        onDelete:"CASCADE"
    )]
    private User $user;



    #[ORM\Column(length:64, unique: true)]
    private string $tokenHash;



    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;



    #[ORM\Column]
    private int $attempts = 0;



    public function getUser(): User
    {
        return $this->user;
    }


    public function setUser(User $user): self
    {
        $this->user=$user;

        return $this;
    }


    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }


    public function setTokenHash(string $token): self
    {
        $this->tokenHash=$token;

        return $this;
    }


    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }


    public function setExpiresAt(\DateTimeImmutable $date): self
    {
        $this->expiresAt=$date;

        return $this;
    }


    public function getAttempts(): int
    {
        return $this->attempts;
    }


    public function increaseAttempts(): self
    {
        $this->attempts++;

        return $this;
    }

}