<?php

namespace App\Entity;

use App\Enum\BookingStatus;
use App\Enum\Currency;
use App\Repository\BookingRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BookingRepository::class)]
class Booking
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'bookings')]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    /**
     * @var array<string, mixed> Raw snapshot of the ride(s) at booking time.
     *                           Original Prisma model kept both this JSON blob
     *                           and a normalized rideId FK - preserved as-is here.
     */
    #[ORM\Column(type: 'json')]
    private array $rides = [];

    #[ORM\ManyToOne(targetEntity: Ride::class, inversedBy: 'bookings')]
    #[ORM\JoinColumn(name: 'ride_id', nullable: true)]
    private ?Ride $ride = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $passengers = null;

    #[ORM\Column(type: 'float')]
    private float $amount;

    #[ORM\Column(type: 'string', enumType: Currency::class)]
    private Currency $currency = Currency::USD;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $address = [];

    #[ORM\Column(type: 'string', enumType: BookingStatus::class)]
    private BookingStatus $status = BookingStatus::PENDING_APPROVAL;

    #[ORM\Column(type: 'boolean')]
    private bool $payment = false;

    #[ORM\Column(type: 'string', length: 255)]
    private string $email;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $bookingDate;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, Notification> */
    #[ORM\OneToMany(targetEntity: Notification::class, mappedBy: 'booking')]
    private Collection $notifications;

    public function __construct()
    {
        $this->notifications = new ArrayCollection();
        $this->bookingDate = new \DateTimeImmutable();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
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

    public function getRides(): array
    {
        return $this->rides;
    }

    public function setRides(array $rides): static
    {
        $this->rides = $rides;
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

    public function getPassengers(): ?int
    {
        return $this->passengers;
    }

    public function setPassengers(?int $passengers): static
    {
        $this->passengers = $passengers;
        return $this;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): static
    {
        $this->amount = $amount;
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

    public function getAddress(): array
    {
        return $this->address;
    }

    public function setAddress(array $address): static
    {
        $this->address = $address;
        return $this;
    }

    public function getStatus(): BookingStatus
    {
        return $this->status;
    }

    public function setStatus(BookingStatus $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function isPayment(): bool
    {
        return $this->payment;
    }

    public function setPayment(bool $payment): static
    {
        $this->payment = $payment;
        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getBookingDate(): \DateTimeImmutable
    {
        return $this->bookingDate;
    }

    public function setBookingDate(\DateTimeImmutable $bookingDate): static
    {
        $this->bookingDate = $bookingDate;
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

    /** @return Collection<int, Notification> */
    public function getNotifications(): Collection
    {
        return $this->notifications;
    }
}
