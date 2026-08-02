<?php

namespace App\Entity;

use App\Repository\FareHistoryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FareHistoryRepository::class)]
class FareHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Ride::class, inversedBy: 'fareHistory')]
    #[ORM\JoinColumn(name: 'ride_id', nullable: false)]
    private Ride $ride;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $previousFare = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $updatedFare = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $calculatedExpectedFare = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRide(): Ride
    {
        return $this->ride;
    }

    public function setRide(Ride $ride): static
    {
        $this->ride = $ride;
        return $this;
    }

    public function getPreviousFare(): ?float
    {
        return $this->previousFare;
    }

    public function setPreviousFare(?float $previousFare): static
    {
        $this->previousFare = $previousFare;
        return $this;
    }

    public function getUpdatedFare(): ?float
    {
        return $this->updatedFare;
    }

    public function setUpdatedFare(?float $updatedFare): static
    {
        $this->updatedFare = $updatedFare;
        return $this;
    }

    public function getCalculatedExpectedFare(): ?float
    {
        return $this->calculatedExpectedFare;
    }

    public function setCalculatedExpectedFare(?float $calculatedExpectedFare): static
    {
        $this->calculatedExpectedFare = $calculatedExpectedFare;
        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}
