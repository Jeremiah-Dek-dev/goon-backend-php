<?php

namespace App\Entity;

use App\Repository\AdminProfileRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AdminProfileRepository::class)]
class AdminProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: User::class, inversedBy: 'adminProfile')]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, unique: true, onDelete: 'CASCADE')]
    private User $user;

    // Store encrypted at rest via a Doctrine transformation type or app-level
    // encryption - do not persist plaintext TOTP secrets.
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $twoFASecret = null;

    #[ORM\Column(type: 'boolean')]
    private bool $is2FAVerified = false;

    #[ORM\Column(type: 'boolean')]
    private bool $isDisabled = false;

    /** @var Collection<int, BackupCode> */
    #[ORM\OneToMany(targetEntity: BackupCode::class, mappedBy: 'adminProfile', orphanRemoval: true)]
    private Collection $backupCodes;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $backupCodesGeneratedAt = null;

    #[ORM\Column(type: 'integer')]
    private int $failedLoginAttempts = 0;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lockUntil = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->backupCodes = new ArrayCollection();
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

    public function getTwoFASecret(): ?string
    {
        return $this->twoFASecret;
    }

    public function setTwoFASecret(?string $twoFASecret): static
    {
        $this->twoFASecret = $twoFASecret;
        return $this;
    }

    public function isIs2FAVerified(): bool
    {
        return $this->is2FAVerified;
    }

    public function setIs2FAVerified(bool $is2FAVerified): static
    {
        $this->is2FAVerified = $is2FAVerified;
        return $this;
    }

    public function isDisabled(): bool
    {
        return $this->isDisabled;
    }

    public function setIsDisabled(bool $isDisabled): static
    {
        $this->isDisabled = $isDisabled;
        return $this;
    }

    /** @return Collection<int, BackupCode> */
    public function getBackupCodes(): Collection
    {
        return $this->backupCodes;
    }

    public function getBackupCodesGeneratedAt(): ?\DateTimeImmutable
    {
        return $this->backupCodesGeneratedAt;
    }

    public function setBackupCodesGeneratedAt(?\DateTimeImmutable $backupCodesGeneratedAt): static
    {
        $this->backupCodesGeneratedAt = $backupCodesGeneratedAt;
        return $this;
    }

    public function getFailedLoginAttempts(): int
    {
        return $this->failedLoginAttempts;
    }

    public function setFailedLoginAttempts(int $failedLoginAttempts): static
    {
        $this->failedLoginAttempts = $failedLoginAttempts;
        return $this;
    }

    public function getLockUntil(): ?\DateTimeImmutable
    {
        return $this->lockUntil;
    }

    public function setLockUntil(?\DateTimeImmutable $lockUntil): static
    {
        $this->lockUntil = $lockUntil;
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
