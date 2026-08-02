<?php

namespace App\Entity;

use App\Enum\Platform;
use App\Repository\DeviceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DeviceRepository::class)]
#[ORM\Index(columns: ['last_updated'], name: 'idx_device_last_updated')]
class Device
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'devices')]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $fcmToken;

    #[ORM\Column(type: 'string', enumType: Platform::class)]
    private Platform $platform;

    #[ORM\Column(type: 'string', length: 32, nullable: true)]
    private ?string $appVersion = null;

    #[ORM\Column(name: 'last_updated', type: 'datetime_immutable')]
    private \DateTimeImmutable $lastUpdated;

    public function __construct()
    {
        $this->lastUpdated = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getFcmToken(): string
    {
        return $this->fcmToken;
    }

    public function setFcmToken(string $fcmToken): static
    {
        $this->fcmToken = $fcmToken;
        return $this;
    }

    public function getPlatform(): Platform
    {
        return $this->platform;
    }

    public function setPlatform(Platform $platform): static
    {
        $this->platform = $platform;
        return $this;
    }

    public function getAppVersion(): ?string
    {
        return $this->appVersion;
    }

    public function setAppVersion(?string $appVersion): static
    {
        $this->appVersion = $appVersion;
        return $this;
    }

    public function getLastUpdated(): \DateTimeImmutable
    {
        return $this->lastUpdated;
    }

    public function setLastUpdated(\DateTimeImmutable $lastUpdated): static
    {
        $this->lastUpdated = $lastUpdated;
        return $this;
    }
}
