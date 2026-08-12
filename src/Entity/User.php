<?php

namespace App\Entity;

use App\Enum\UserRole;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'user')]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private string $email;

    // Nullable because of Google OAuth accounts that never set a local password
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $password = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $avatar = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true, unique: true)]
    private ?string $googleId = null;

    #[ORM\Column(type: 'boolean')]
    private bool $verified = false;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $resetToken = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $resetTokenExpires = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true, unique: true)]
    private ?string $fcmToken = null;

    #[ORM\Column(type: 'integer')]
    private int $failedLoginAttempts = 0;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lockUntil = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastLoginAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastActiveAt = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isOnline = false;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, RefreshToken> */
    #[ORM\OneToMany(targetEntity: RefreshToken::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $refreshTokens;

    /** @var Collection<int, Ride> Rides where this user is the driver (see Ride::$driver, relation name "UserRides") */
    #[ORM\OneToMany(targetEntity: Ride::class, mappedBy: 'driver')]
    private Collection $rides;

    /** @var Collection<int, Rating> Ratings this user has given to others */
    #[ORM\OneToMany(targetEntity: Rating::class, mappedBy: 'rater')]
    private Collection $ratingsGiven;

    /** @var Collection<int, Rating> Ratings this user has received from others */
    #[ORM\OneToMany(targetEntity: Rating::class, mappedBy: 'ratee')]
    private Collection $ratingsReceived;

    /** @var Collection<int, Booking> */
    #[ORM\OneToMany(targetEntity: Booking::class, mappedBy: 'user')]
    private Collection $bookings;

    /** @var Collection<int, UserRoleAssignment> */
    #[ORM\OneToMany(targetEntity: UserRoleAssignment::class, mappedBy: 'user', orphanRemoval: true, cascade: ['persist'])]
    private Collection $roleAssignments;

    /** @var Collection<int, OTP> */
    #[ORM\OneToMany(targetEntity: OTP::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $otps;

    /** @var Collection<int, Notification> */
    #[ORM\OneToMany(targetEntity: Notification::class, mappedBy: 'user')]
    private Collection $notifications;

    #[ORM\OneToOne(targetEntity: DriverProfile::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    private ?DriverProfile $driverProfile = null;

    /** @var Collection<int, Device> */
    #[ORM\OneToMany(targetEntity: Device::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $devices;

    #[ORM\OneToOne(targetEntity: AdminProfile::class, mappedBy: 'user', cascade: ['persist', 'remove'])]
    private ?AdminProfile $adminProfile = null;

    /** @var Collection<int, AdminInvite> */
    #[ORM\OneToMany(targetEntity: AdminInvite::class, mappedBy: 'createdBy')]
    private Collection $createdInvites;

    /** @var Collection<int, ActivityLog> */
    #[ORM\OneToMany(targetEntity: ActivityLog::class, mappedBy: 'user')]
    private Collection $activityLogs;

    /** @var Collection<int, CartItem> */
    #[ORM\OneToMany(targetEntity: CartItem::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $cartItems;

    public function __construct()
    {
        $this->refreshTokens = new ArrayCollection();
        $this->rides = new ArrayCollection();
        $this->ratingsGiven = new ArrayCollection();
        $this->ratingsReceived = new ArrayCollection();
        $this->bookings = new ArrayCollection();
        $this->roleAssignments = new ArrayCollection();
        $this->otps = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->devices = new ArrayCollection();
        $this->createdInvites = new ArrayCollection();
        $this->activityLogs = new ArrayCollection();
        $this->cartItems = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function addRole(UserRole $role): void
    {
        foreach($this->roleAssignments as $assignment){
            if($assignment->getRole() === $role){
                return;
            }
        }

        $assignment = new UserRoleAssignment();
        $assignment->setUser($this);
        $assignment->setRole($role);

        $this->roleAssignments->add($assignment);
    }

    public function addOtp(OTP $otp): static
    {
        if (!$this->otps->contains($otp)) {
            $this->otps->add($otp);
            $otp->setUser($this);
        }

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
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

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): static
    {
        $this->avatar = $avatar;
        return $this;
    }

    public function getGoogleId(): ?string
    {
        return $this->googleId;
    }

    public function setGoogleId(?string $googleId): static
    {
        $this->googleId = $googleId;
        return $this;
    }

    public function isVerified(): bool
    {
        return $this->verified;
    }

    public function setVerified(bool $verified): static
    {
        $this->verified = $verified;
        return $this;
    }

    public function getResetToken(): ?string
    {
        return $this->resetToken;
    }

    public function setResetToken(?string $resetToken): static
    {
        $this->resetToken = $resetToken;
        return $this;
    }

    public function getResetTokenExpires(): ?\DateTimeImmutable
    {
        return $this->resetTokenExpires;
    }

    public function setResetTokenExpires(?\DateTimeImmutable $resetTokenExpires): static
    {
        $this->resetTokenExpires = $resetTokenExpires;
        return $this;
    }

    public function getFcmToken(): ?string
    {
        return $this->fcmToken;
    }

    public function setFcmToken(?string $fcmToken): static
    {
        $this->fcmToken = $fcmToken;
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

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?\DateTimeImmutable $lastLoginAt): static
    {
        $this->lastLoginAt = $lastLoginAt;
        return $this;
    }

    public function getLastActiveAt(): ?\DateTimeImmutable
    {
        return $this->lastActiveAt;
    }

    public function setLastActiveAt(?\DateTimeImmutable $lastActiveAt): static
    {
        $this->lastActiveAt = $lastActiveAt;
        return $this;
    }

    public function isOnline(): bool
    {
        return $this->isOnline;
    }

    public function setIsOnline(bool $isOnline): static
    {
        $this->isOnline = $isOnline;
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

    /** @return Collection<int, RefreshToken> */
    public function getRefreshTokens(): Collection
    {
        return $this->refreshTokens;
    }

    /** @return Collection<int, Ride> */
    public function getRides(): Collection
    {
        return $this->rides;
    }

    /** @return Collection<int, Rating> */
    public function getRatingsGiven(): Collection
    {
        return $this->ratingsGiven;
    }

    /** @return Collection<int, Rating> */
    public function getRatingsReceived(): Collection
    {
        return $this->ratingsReceived;
    }

    /** @return Collection<int, Booking> */
    public function getBookings(): Collection
    {
        return $this->bookings;
    }

    /** @return Collection<int, UserRoleAssignment> */
    public function getRoleAssignments(): Collection
    {
        return $this->roleAssignments;
    }

    /** @return Collection<int, OTP> */
    public function getOtps(): Collection
    {
        return $this->otps;
    }

    /** @return Collection<int, Notification> */
    public function getNotifications(): Collection
    {
        return $this->notifications;
    }

    public function getDriverProfile(): ?DriverProfile
    {
        return $this->driverProfile;
    }

    /** @return Collection<int, Device> */
    public function getDevices(): Collection
    {
        return $this->devices;
    }

    public function getAdminProfile(): ?AdminProfile
    {
        return $this->adminProfile;
    }

    /** @return Collection<int, AdminInvite> */
    public function getCreatedInvites(): Collection
    {
        return $this->createdInvites;
    }

    /** @return Collection<int, ActivityLog> */
    public function getActivityLogs(): Collection
    {
        return $this->activityLogs;
    }

    /** @return Collection<int, CartItem> */
    public function getCartItems(): Collection
    {
        return $this->cartItems;
    }

    // --- Symfony Security UserInterface ---

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * @return list<string> Derived from UserRoleAssignment records at runtime;
     *                       load with a dedicated query rather than lazy-loading
     *                       roleAssignments on every security check.
     */
    public function getRoles(): array
    {
        $roles = [
            'ROLE_USER'
        ];


        foreach ($this->roleAssignments as $assignment) {

            $roles[] =
                'ROLE_'.$assignment
                ->getRole()
                ->value;

        }


        return array_unique($roles);
    }

    public function eraseCredentials(): void
    {
        // No plaintext sensitive data stored on the entity to purge.
    }
}
