<?php

namespace App\Entity;

use App\Repository\RatingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RatingRepository::class)]
class Rating
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Ride::class, inversedBy: 'ratings')]
    #[ORM\JoinColumn(name: 'ride_id', nullable: false)]
    private Ride $ride;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'ratingsGiven')]
    #[ORM\JoinColumn(name: 'rater_id', nullable: false)]
    private User $rater;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'ratingsReceived')]
    #[ORM\JoinColumn(name: 'ratee_id', nullable: false)]
    private User $ratee;

    #[ORM\Column(type: 'integer')]
    private int $score;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment = null;

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

    public function getRide(): Ride
    {
        return $this->ride;
    }

    public function setRide(Ride $ride): static
    {
        $this->ride = $ride;
        return $this;
    }

    public function getRater(): User
    {
        return $this->rater;
    }

    public function setRater(User $rater): static
    {
        $this->rater = $rater;
        return $this;
    }

    public function getRatee(): User
    {
        return $this->ratee;
    }

    public function setRatee(User $ratee): static
    {
        $this->ratee = $ratee;
        return $this;
    }

    public function getScore(): int
    {
        return $this->score;
    }

    public function setScore(int $score): static
    {
        $this->score = $score;
        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): static
    {
        $this->comment = $comment;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
