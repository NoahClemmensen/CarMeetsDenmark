<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TeamRole;
use App\Repository\TeamMemberRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TeamMemberRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_TEAM_MEMBER', columns: ['team_id', 'user_id'])]
class TeamMember
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'members')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Team $team = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 20, enumType: TeamRole::class)]
    private TeamRole $role;

    #[ORM\Column]
    private int $joinedAt;

    public function __construct(Team $team, User $user, TeamRole $role)
    {
        $this->team = $team;
        $this->user = $user;
        $this->role = $role;
        $this->joinedAt = time();
    }

    public function getId(): ?int
    {
        return $this->id;
    }
    public function getTeam(): ?Team
    {
        return $this->team;
    }
    public function getUser(): ?User
    {
        return $this->user;
    }
    public function getRole(): TeamRole
    {
        return $this->role;
    }
    public function setRole(TeamRole $role): static
    {
        $this->role = $role;
        return $this;
    }
    public function getJoinedAt(): int
    {
        return $this->joinedAt;
    }

    public function isOwner(): bool
    {
        return $this->role === TeamRole::Owner;
    }
}
