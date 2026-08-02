<?php

namespace App\Entity;

use App\Enum\DriverStatus;
use App\Enum\VehicleType;
use App\Repository\DriverProfileRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DriverProfileRepository::class)]
#[ORM\Index(columns: ['location_lat', 'location_lng'], name: 'idx_driver_location')]
class DriverProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: User::class, inversedBy: 'driverProfile')]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, unique: true, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'string', length: 32)]
    private string $phone;

    #[ORM\Column(type: 'string', length: 64)]
    private string $licenseNumber;

    #[ORM\Column(type: 'string', enumType: VehicleType::class)]
    private VehicleType $vehicleType;

    #[ORM\Column(type: 'string', length: 128)]
    private string $model;

    #[ORM\Column(type: 'string', length: 64)]
    private string $registrationNumber;

    #[ORM\Column(type: 'integer')]
    private int $capacity = 4;

    #[ORM\Column(type: 'float')]
    private float $rating = 0;

    #[ORM\Column(type: 'integer')]
    private int $totalRides = 0;

    #[ORM\Column(type: 'string', enumType: DriverStatus::class)]
    private DriverStatus $status = DriverStatus::PENDING;

    #[ORM\Column(type: 'boolean')]
    private bool $approved = false;

    #[ORM\Column(type: 'integer')]
    private int $maxPassengers = 4;

    #[ORM\Column(type: 'boolean')]
    private bool $isAvailable = true;

    /** @var array<string, mixed>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $documents = null;

    #[ORM\Column(name: 'location_lat', type: 'float', nullable: true)]
    private ?float $locationLat = null;

    #[ORM\Column(name: 'location_lng', type: 'float', nullable: true)]
    private ?float $locationLng = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
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

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): static
    {
        $this->phone = $phone;
        return $this;
    }

    public function getLicenseNumber(): string
    {
        return $this->licenseNumber;
    }

    public function setLicenseNumber(string $licenseNumber): static
    {
        $this->licenseNumber = $licenseNumber;
        return $this;
    }

    public function getVehicleType(): VehicleType
    {
        return $this->vehicleType;
    }

    public function setVehicleType(VehicleType $vehicleType): static
    {
        $this->vehicleType = $vehicleType;
        return $this;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function setModel(string $model): static
    {
        $this->model = $model;
        return $this;
    }

    public function getRegistrationNumber(): string
    {
        return $this->registrationNumber;
    }

    public function setRegistrationNumber(string $registrationNumber): static
    {
        $this->registrationNumber = $registrationNumber;
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

    public function getRating(): float
    {
        return $this->rating;
    }

    public function setRating(float $rating): static
    {
        $this->rating = $rating;
        return $this;
    }

    public function getTotalRides(): int
    {
        return $this->totalRides;
    }

    public function setTotalRides(int $totalRides): static
    {
        $this->totalRides = $totalRides;
        return $this;
    }

    public function getStatus(): DriverStatus
    {
        return $this->status;
    }

    public function setStatus(DriverStatus $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function isApproved(): bool
    {
        return $this->approved;
    }

    public function setApproved(bool $approved): static
    {
        $this->approved = $approved;
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

    public function isAvailable(): bool
    {
        return $this->isAvailable;
    }

    public function setIsAvailable(bool $isAvailable): static
    {
        $this->isAvailable = $isAvailable;
        return $this;
    }

    public function getDocuments(): ?array
    {
        return $this->documents;
    }

    public function setDocuments(?array $documents): static
    {
        $this->documents = $documents;
        return $this;
    }

    public function getLocationLat(): ?float
    {
        return $this->locationLat;
    }

    public function setLocationLat(?float $locationLat): static
    {
        $this->locationLat = $locationLat;
        return $this;
    }

    public function getLocationLng(): ?float
    {
        return $this->locationLng;
    }

    public function setLocationLng(?float $locationLng): static
    {
        $this->locationLng = $locationLng;
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
