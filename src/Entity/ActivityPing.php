<?php

namespace App\Entity;

use App\Repository\ActivityPingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ActivityPingRepository::class)]
#[ORM\Index(name: 'idx_activity_ping_expires_at', columns: ['expires_at'])]
class ActivityPing
{
    /** Lifetime of a ping in seconds (3 hours). */
    public const int LIFETIME_SECONDS = 3 * 60 * 60;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: Types::DECIMAL, precision: 9, scale: 6)]
    private string $lat;

    #[ORM\Column(type: Types::DECIMAL, precision: 9, scale: 6)]
    private string $lng;

    #[ORM\Column]
    private int $createdAt;

    #[ORM\Column]
    private int $expiresAt;

    public function __construct(User $user, float $lat, float $lng)
    {
        $this->user = $user;
        $this->lat = (string) $lat;
        $this->lng = (string) $lng;
        $this->createdAt = time();
        $this->expiresAt = $this->createdAt + self::LIFETIME_SECONDS;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getLat(): float
    {
        return (float) $this->lat;
    }

    public function getLng(): float
    {
        return (float) $this->lng;
    }

    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    public function getExpiresAt(): int
    {
        return $this->expiresAt;
    }

    public function isActive(): bool
    {
        return $this->expiresAt > time();
    }
}
