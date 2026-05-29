<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\FollowTargetType;
use App\Repository\FollowRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FollowRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_FOLLOW_USER', columns: ['follower_id', 'target_user_id'])]
#[ORM\UniqueConstraint(name: 'UNIQ_FOLLOW_TEAM', columns: ['follower_id', 'target_team_id'])]
class Follow
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $follower;

    #[ORM\Column(length: 10, enumType: FollowTargetType::class)]
    private FollowTargetType $targetType;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?User $targetUser = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Team $targetTeam = null;

    #[ORM\Column]
    private int $createdAt;

    public function __construct(User $follower, FollowTargetType $type)
    {
        $this->follower = $follower;
        $this->targetType = $type;
        $this->createdAt = time();
    }

    public static function forUser(User $follower, User $target): self
    {
        $follow = new self($follower, FollowTargetType::User);
        $follow->targetUser = $target;
        return $follow;
    }

    public static function forTeam(User $follower, Team $target): self
    {
        $follow = new self($follower, FollowTargetType::Team);
        $follow->targetTeam = $target;
        return $follow;
    }

    public function getId(): ?int
    {
        return $this->id;
    }
    public function getFollower(): User
    {
        return $this->follower;
    }
    public function getTargetType(): FollowTargetType
    {
        return $this->targetType;
    }
    public function getTargetUser(): ?User
    {
        return $this->targetUser;
    }
    public function getTargetTeam(): ?Team
    {
        return $this->targetTeam;
    }
    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }
}
