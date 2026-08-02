<?php

namespace App\Entity;

use App\Enum\NotificationStatus;
use App\Enum\NotificationType;
use App\Repository\NotificationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
class Notification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true, unique: true)]
    private ?string $jobId = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'notifications')]
    #[ORM\JoinColumn(name: 'user_id', nullable: true, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Ride::class, inversedBy: 'notifications')]
    #[ORM\JoinColumn(name: 'ride_id', nullable: true)]
    private ?Ride $ride = null;

    #[ORM\ManyToOne(targetEntity: Booking::class, inversedBy: 'notifications')]
    #[ORM\JoinColumn(name: 'booking_id', nullable: true)]
    private ?Booking $booking = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $title;

    #[ORM\Column(type: 'text')]
    private string $body;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $message = 'Welcome back to GoOn. Start booking your rides now!';

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $data = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $extra = null;

    #[ORM\Column(type: 'string', enumType: NotificationType::class)]
    private NotificationType $type = NotificationType::SYSTEM;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $scheduledAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column(type: 'string', enumType: NotificationStatus::class)]
    private NotificationStatus $status = NotificationStatus::PENDING;

    #[ORM\Column(type: 'integer')]
    private int $attempts = 0;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $lastError = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isRead = false;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->scheduledAt = new \DateTimeImmutable();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getJobId(): ?string
    {
        return $this->jobId;
    }

    public function setJobId(?string $jobId): static
    {
        $this->jobId = $jobId;
        return $this;
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

    public function getRide(): ?Ride
    {
        return $this->ride;
    }

    public function setRide(?Ride $ride): static
    {
        $this->ride = $ride;
        return $this;
    }

    public function getBooking(): ?Booking
    {
        return $this->booking;
    }

    public function setBooking(?Booking $booking): static
    {
        $this->booking = $booking;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): static
    {
        $this->body = $body;
        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = $message;
        return $this;
    }

    public function getData(): ?array
    {
        return $this->data;
    }

    public function setData(?array $data): static
    {
        $this->data = $data;
        return $this;
    }

    public function getExtra(): ?array
    {
        return $this->extra;
    }

    public function setExtra(?array $extra): static
    {
        $this->extra = $extra;
        return $this;
    }

    public function getType(): NotificationType
    {
        return $this->type;
    }

    public function setType(NotificationType $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getScheduledAt(): \DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function setScheduledAt(\DateTimeImmutable $scheduledAt): static
    {
        $this->scheduledAt = $scheduledAt;
        return $this;
    }

    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function setSentAt(?\DateTimeImmutable $sentAt): static
    {
        $this->sentAt = $sentAt;
        return $this;
    }

    public function getStatus(): NotificationStatus
    {
        return $this->status;
    }

    public function setStatus(NotificationStatus $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function setAttempts(int $attempts): static
    {
        $this->attempts = $attempts;
        return $this;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function setLastError(?string $lastError): static
    {
        $this->lastError = $lastError;
        return $this;
    }

    public function isRead(): bool
    {
        return $this->isRead;
    }

    public function setIsRead(bool $isRead): static
    {
        $this->isRead = $isRead;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PreUpdate]
    public function touchUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
