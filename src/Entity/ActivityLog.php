<?php

namespace App\Entity;

use App\Repository\ActivityLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ActivityLogRepository::class)]
class ActivityLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'activityLogs')]
    #[ORM\JoinColumn(name: 'user_id', nullable: true)]
    private ?User $user = null;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $role = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $action;

    #[ORM\Column(type: 'text')]
    private string $description;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $rawUserAgent = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(?string $role): static
    {
        $this->role = $role;
        return $this;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    public function getRawUserAgent(): ?string
    {
        return $this->rawUserAgent;
    }

    public function setRawUserAgent(?string $rawUserAgent): static
    {
        $this->rawUserAgent = $rawUserAgent;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
