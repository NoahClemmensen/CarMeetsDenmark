<?php

namespace App\Entity;

use App\Enum\EmbedProvider;
use App\Repository\PostRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: PostRepository::class)]
#[ORM\Index(name: 'idx_post_event_created', columns: ['event_id', 'created_at'])]
#[ORM\Index(name: 'idx_post_pinned_at', columns: ['pinned_at'])]
class Post
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::GUID)]
    private string $uuid;

    #[ORM\ManyToOne(inversedBy: 'posts')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Event $event;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $author;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $body = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $link = null;

    #[ORM\Column(length: 20, nullable: true, enumType: EmbedProvider::class)]
    private ?EmbedProvider $embedProvider = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $embedExternalId = null;

    #[ORM\Column(name: 'hype_count', options: ['default' => 0])]
    private int $hypeCount = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $pinnedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $editedAt = null;

    #[ORM\Column]
    private int $createdAt;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isDeleted = false;

    /** @var Collection<int, PostImage> */
    #[ORM\OneToMany(targetEntity: PostImage::class, mappedBy: 'post', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $images;

    public function __construct(Event $event, ?User $author)
    {
        $this->uuid = Uuid::v4()->toRfc4122();
        $this->event = $event;
        $this->author = $author;
        $this->createdAt = time();
        $this->images = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }
    public function getUuid(): string
    {
        return $this->uuid;
    }
    public function getEvent(): Event
    {
        return $this->event;
    }
    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }
    public function setBody(?string $body): static
    {
        $this->body = $body;
        return $this;
    }

    public function getLink(): ?string
    {
        return $this->link;
    }
    public function setLink(?string $link): static
    {
        $this->link = $link;
        return $this;
    }

    public function getEmbedProvider(): ?EmbedProvider
    {
        return $this->embedProvider;
    }
    public function setEmbedProvider(?EmbedProvider $provider): static
    {
        $this->embedProvider = $provider;
        return $this;
    }

    public function getEmbedExternalId(): ?string
    {
        return $this->embedExternalId;
    }
    public function setEmbedExternalId(?string $id): static
    {
        $this->embedExternalId = $id;
        return $this;
    }

    public function getHypeCount(): int
    {
        return $this->hypeCount;
    }
    public function setHypeCount(int $count): static
    {
        $this->hypeCount = $count;
        return $this;
    }

    public function getPinnedAt(): ?DateTimeImmutable
    {
        return $this->pinnedAt;
    }
    public function setPinnedAt(?DateTimeImmutable $pinnedAt): static
    {
        $this->pinnedAt = $pinnedAt;
        return $this;
    }
    public function isPinned(): bool
    {
        return $this->pinnedAt !== null;
    }

    public function getEditedAt(): ?DateTimeImmutable
    {
        return $this->editedAt;
    }
    public function setEditedAt(?DateTimeImmutable $editedAt): static
    {
        $this->editedAt = $editedAt;
        return $this;
    }

    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    public function isDeleted(): bool
    {
        return $this->isDeleted;
    }
    public function setIsDeleted(bool $isDeleted): static
    {
        $this->isDeleted = $isDeleted;
        return $this;
    }

    /** @return Collection<int, PostImage> */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImage(PostImage $image): static
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
        }
        return $this;
    }

    public function hasEmbed(): bool
    {
        return $this->embedProvider !== null && $this->embedExternalId !== null;
    }
}
