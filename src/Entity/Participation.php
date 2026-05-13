<?php

namespace App\Entity;

use App\Enum\ParticipationStatus;
use App\Repository\ParticipationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ParticipationRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_participation_event_user', columns: ['event_id', 'user_id'])]
class Participation
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

    #[ORM\Column(length: 20, enumType: ParticipationStatus::class)]
    private ParticipationStatus $status;

    #[ORM\Column]
    private int $joinedAt;

    public function __construct(Event $event, User $user, ParticipationStatus $status)
    {
        $this->event = $event;
        $this->user = $user;
        $this->status = $status;
        $this->joinedAt = time();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEvent(): Event
    {
        return $this->event;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getStatus(): ParticipationStatus
    {
        return $this->status;
    }

    public function setStatus(ParticipationStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getJoinedAt(): int
    {
        return $this->joinedAt;
    }
}
