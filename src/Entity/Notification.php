<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\NotificationType;
use App\Repository\NotificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Index(name: 'idx_notification_recipient_created', columns: ['recipient_id', 'created_at'])]
#[ORM\Index(name: 'idx_notification_recipient_read', columns: ['recipient_id', 'read_at'])]
class Notification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::GUID)]
    private string $uuid;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $recipient;

    #[ORM\Column(length: 32, enumType: NotificationType::class)]
    private NotificationType $type;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?User $actorUser = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Team $actorTeam = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Event $targetEvent = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Post $targetPost = null;

    #[ORM\Column]
    private int $createdAt;

    #[ORM\Column(nullable: true)]
    private ?int $readAt = null;

    public function __construct(User $recipient, NotificationType $type)
    {
        $this->uuid = Uuid::v4()->toRfc4122();
        $this->recipient = $recipient;
        $this->type = $type;
        $this->createdAt = time();
    }

    public function getId(): ?int
    {
        return $this->id;
    }
    public function getUuid(): string
    {
        return $this->uuid;
    }
    public function getRecipient(): User
    {
        return $this->recipient;
    }
    public function getType(): NotificationType
    {
        return $this->type;
    }

    public function getActorUser(): ?User
    {
        return $this->actorUser;
    }
    public function setActorUser(?User $actorUser): static
    {
        $this->actorUser = $actorUser;
        return $this;
    }

    public function getActorTeam(): ?Team
    {
        return $this->actorTeam;
    }
    public function setActorTeam(?Team $actorTeam): static
    {
        $this->actorTeam = $actorTeam;
        return $this;
    }

    public function getTargetEvent(): ?Event
    {
        return $this->targetEvent;
    }
    public function setTargetEvent(?Event $targetEvent): static
    {
        $this->targetEvent = $targetEvent;
        return $this;
    }

    public function getTargetPost(): ?Post
    {
        return $this->targetPost;
    }
    public function setTargetPost(?Post $targetPost): static
    {
        $this->targetPost = $targetPost;
        return $this;
    }

    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    public function getReadAt(): ?int
    {
        return $this->readAt;
    }
    public function setReadAt(?int $readAt): static
    {
        $this->readAt = $readAt;
        return $this;
    }
    public function isRead(): bool
    {
        return $this->readAt !== null;
    }
}
