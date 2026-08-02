<?php

namespace App\Entity;

use App\Enum\Currency;
use App\Enum\RideStatus;
use App\Repository\RideRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RideRepository::class)]
class Ride
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $pickup = [];

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $destination = [];

    #[ORM\Column(type: 'string', length: 255)]
    private string $pickupNorm;

    #[ORM\Column(type: 'string', length: 255)]
    private string $destinationNorm;

    #[ORM\Column(type: 'float')]
    private float $price;

    #[ORM\Column(type: 'string', enumType: Currency::class)]
    private Currency $currency = Currency::USD;

    #[ORM\Column(type: 'float')]
    private float $commissionRate;

    #[ORM\Column(type: 'float')]
    private float $commissionAmount;

    #[ORM\Column(type: 'float')]
    private float $payoutAmount;

    #[ORM\Column(type: 'text')]
    private string $description;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $selectedDate;

    #[ORM\Column(type: 'string', length: 64)]
    private string $selectedTime;

    #[ORM\Column(type: 'integer')]
    private int $capacity;

    #[ORM\Column(type: 'integer')]
    private int $maxPassengers;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $imageUrl = null;

    #[ORM\Column(type: 'string', length: 64)]
    private string $type;

    #[ORM\Column(type: 'string', enumType: RideStatus::class)]
    private RideStatus $status = RideStatus::SCHEDULED;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $distance = null;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $duration = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'rides')]
    #[ORM\JoinColumn(name: 'driver_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $driver = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, FareHistory> */
    #[ORM\OneToMany(targetEntity: FareHistory::class, mappedBy: 'ride')]
    private Collection $fareHistory;

    /** @var Collection<int, Rating> */
    #[ORM\OneToMany(targetEntity: Rating::class, mappedBy: 'ride')]
    private Collection $ratings;

    /** @var Collection<int, Booking> */
    #[ORM\OneToMany(targetEntity: Booking::class, mappedBy: 'ride')]
    private Collection $bookings;

    /** @var Collection<int, Notification> */
    #[ORM\OneToMany(targetEntity: Notification::class, mappedBy: 'ride')]
    private Collection $notifications;

    public function __construct()
    {
        $this->fareHistory = new ArrayCollection();
        $this->ratings = new ArrayCollection();
        $this->bookings = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPickup(): array
    {
        return $this->pickup;
    }

    public function setPickup(array $pickup): static
    {
        $this->pickup = $pickup;
        return $this;
    }

    public function getDestination(): array
    {
        return $this->destination;
    }

    public function setDestination(array $destination): static
    {
        $this->destination = $destination;
        return $this;
    }

    public function getPickupNorm(): string
    {
        return $this->pickupNorm;
    }

    public function setPickupNorm(string $pickupNorm): static
    {
        $this->pickupNorm = $pickupNorm;
        return $this;
    }

    public function getDestinationNorm(): string
    {
        return $this->destinationNorm;
    }

    public function setDestinationNorm(string $destinationNorm): static
    {
        $this->destinationNorm = $destinationNorm;
        return $this;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function setPrice(float $price): static
    {
        $this->price = $price;
        return $this;
    }

    public function getCurrency(): Currency
    {
        return $this->currency;
    }

    public function setCurrency(Currency $currency): static
    {
        $this->currency = $currency;
        return $this;
    }

    public function getCommissionRate(): float
    {
        return $this->commissionRate;
    }

    public function setCommissionRate(float $commissionRate): static
    {
        $this->commissionRate = $commissionRate;
        return $this;
    }

    public function getCommissionAmount(): float
    {
        return $this->commissionAmount;
    }

    public function setCommissionAmount(float $commissionAmount): static
    {
        $this->commissionAmount = $commissionAmount;
        return $this;
    }

    public function getPayoutAmount(): float
    {
        return $this->payoutAmount;
    }

    public function setPayoutAmount(float $payoutAmount): static
    {
        $this->payoutAmount = $payoutAmount;
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

    public function getSelectedDate(): \DateTimeImmutable
    {
        return $this->selectedDate;
    }

    public function setSelectedDate(\DateTimeImmutable $selectedDate): static
    {
        $this->selectedDate = $selectedDate;
        return $this;
    }

    public function getSelectedTime(): string
    {
        return $this->selectedTime;
    }

    public function setSelectedTime(string $selectedTime): static
    {
        $this->selectedTime = $selectedTime;
        return $this;
    }

    public function getCapacity(): int
    {
        return $this->capacity;
    }

    public function setCapacity(int $capacity): static
    {
        $this->capacity = $capacity;
        return $this;
    }

    public function getMaxPassengers(): int
    {
        return $this->maxPassengers;
    }

    public function setMaxPassengers(int $maxPassengers): static
    {
        $this->maxPassengers = $maxPassengers;
        return $this;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function setImageUrl(?string $imageUrl): static
    {
        $this->imageUrl = $imageUrl;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getStatus(): RideStatus
    {
        return $this->status;
    }

    public function setStatus(RideStatus $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getDistance(): ?float
    {
        return $this->distance;
    }

    public function setDistance(?float $distance): static
    {
        $this->distance = $distance;
        return $this;
    }

    public function getDuration(): ?string
    {
        return $this->duration;
    }

    public function setDuration(?string $duration): static
    {
        $this->duration = $duration;
        return $this;
    }

    public function getDriver(): ?User
    {
        return $this->driver;
    }

    public function setDriver(?User $driver): static
    {
        $this->driver = $driver;
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

    /** @return Collection<int, FareHistory> */
    public function getFareHistory(): Collection
    {
        return $this->fareHistory;
    }

    /** @return Collection<int, Rating> */
    public function getRatings(): Collection
    {
        return $this->ratings;
    }

    /** @return Collection<int, Booking> */
    public function getBookings(): Collection
    {
        return $this->bookings;
    }

    /** @return Collection<int, Notification> */
    public function getNotifications(): Collection
    {
        return $this->notifications;
    }
}
