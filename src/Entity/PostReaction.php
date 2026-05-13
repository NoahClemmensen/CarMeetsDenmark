<?php

namespace App\Entity;

use App\Repository\PostReactionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PostReactionRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_post_reaction_post_user', columns: ['post_id', 'user_id'])]
class PostReaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Post $post;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column]
    private int $createdAt;

    public function __construct(Post $post, User $user)
    {
        $this->post = $post;
        $this->user = $user;
        $this->createdAt = time();
    }

    public function getId(): ?int { return $this->id; }
    public function getPost(): Post { return $this->post; }
    public function getUser(): User { return $this->user; }
    public function getCreatedAt(): int { return $this->createdAt; }
}
