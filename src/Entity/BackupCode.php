<?php

namespace App\Entity;

use App\Repository\BackupCodeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BackupCodeRepository::class)]
class BackupCode
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    // Store hashed (e.g. password_hash), never plaintext - same as the
    // original backup-code recovery pattern should have been doing.
    #[ORM\Column(type: 'string', length: 255)]
    private string $code;

    #[ORM\Column(type: 'boolean')]
    private bool $used = false;

    #[ORM\ManyToOne(targetEntity: AdminProfile::class, inversedBy: 'backupCodes')]
    #[ORM\JoinColumn(name: 'admin_profile_id', nullable: false, onDelete: 'CASCADE')]
    private AdminProfile $adminProfile;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;
        return $this;
    }

    public function isUsed(): bool
    {
        return $this->used;
    }

    public function setUsed(bool $used): static
    {
        $this->used = $used;
        return $this;
    }

    public function getAdminProfile(): AdminProfile
    {
        return $this->adminProfile;
    }

    public function setAdminProfile(AdminProfile $adminProfile): static
    {
        $this->adminProfile = $adminProfile;
        return $this;
    }
}
