<?php

namespace App\Entity;

use App\Repository\PostImageRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PostImageRepository::class)]
class PostImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'images')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Post $post;

    #[ORM\Column(length: 255)]
    private string $filename;

    #[ORM\Column(type: 'smallint')]
    private int $position;

    public function __construct(Post $post, string $filename, int $position)
    {
        $this->post = $post;
        $this->filename = $filename;
        $this->position = $position;
    }

    public function getId(): ?int
    {
        return $this->id;
    }
    public function getPost(): Post
    {
        return $this->post;
    }
    public function getFilename(): string
    {
        return $this->filename;
    }
    public function getPosition(): int
    {
        return $this->position;
    }
}
