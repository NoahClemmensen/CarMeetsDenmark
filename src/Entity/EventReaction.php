<?php

namespace App\Entity;

use App\Repository\EventReactionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EventReactionRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_event_reaction_event_user', columns: ['event_id', 'user_id'])]
class EventReaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Event $event;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column]
    private int $createdAt;

    public function __construct(Event $event, User $user)
    {
        $this->event = $event;
        $this->user = $user;
        $this->createdAt = time();
    }

    public function getId(): ?int { return $this->id; }
    public function getEvent(): Event { return $this->event; }
    public function getUser(): User { return $this->user; }
    public function getCreatedAt(): int { return $this->createdAt; }
}
