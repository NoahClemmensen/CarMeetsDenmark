<?php

namespace App\Entity;

use App\Enum\EventRepeatFrequency;
use App\Repository\EventRepository;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: EventRepository::class)]
class Event
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::GUID)]
    #[Groups(['public', 'sync'])]
    private ?string $uuid = null;

    #[ORM\Column(length: 255)]
    #[Groups(['public'])]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['public'])]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['public'])]
    private ?DateTimeInterface $startDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['public'])]
    private ?DateTimeInterface $endDate = null;

    #[ORM\Column(length: 255)]
    #[Groups(['public'])]
    private ?string $location = null;

    #[ORM\Column]
    #[Groups(['public'])]
    private ?int $createdAt;

    #[ORM\Column(length: 255)]
    private ?string $timezone = null;

    #[ORM\Column(length: 20, nullable: true, enumType: EventRepeatFrequency::class)]
    #[Groups(['public'])]
    private ?EventRepeatFrequency $repeatFrequency = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['public'])]
    private ?int $repeatAmount = null;

    #[ORM\Column(name: 'hype_count', options: ['default' => 0])]
    #[Groups(['public'])]
    private int $hypeCount = 0;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imageFilename = null;

    #[ORM\ManyToOne(inversedBy: 'events')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $host;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Team $team = null;

    #[ORM\Column(type: 'boolean')]
    private bool $private = false;

    #[ORM\Column(type: 'boolean')]
    private bool $isDeleted = false;

    #[ORM\Column(type: 'boolean')]
    private bool $archived = false;

    /** @var Collection<int, Post> */
    #[ORM\OneToMany(mappedBy: 'event', targetEntity: Post::class)]
    private Collection $posts;

    public function __construct(User $user)
    {
        $this->uuid = Uuid::v4()->toRfc4122();
        $this->createdAt = time();
        $this->host = $user;
        $this->posts = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): ?string
    {
        return $this->uuid;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getStartDate(): ?DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(DateTimeInterface $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(?DateTimeInterface $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): static
    {
        $this->location = $location;

        return $this;
    }

    public function getCreatedAt(): ?int
    {
        return $this->createdAt;
    }

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    public function setTimezone(?string $timezone): static
    {
        $this->timezone = $timezone;

        return $this;
    }

    public function getRepeatFrequency(): ?EventRepeatFrequency
    {
        return $this->repeatFrequency;
    }

    public function setRepeatFrequency(?EventRepeatFrequency $repeatFrequency): static
    {
        $this->repeatFrequency = $repeatFrequency;

        return $this;
    }

    public function getRepeatAmount(): ?int
    {
        return $this->repeatAmount;
    }

    public function setRepeatAmount(?int $repeatAmount): static
    {
        $this->repeatAmount = $repeatAmount;

        return $this;
    }

    public function getHypeCount(): int
    {
        return $this->hypeCount;
    }

    public function setHypeCount(int $hypeCount): static
    {
        $this->hypeCount = $hypeCount;

        return $this;
    }

    public function getImageFilename(): ?string
    {
        return $this->imageFilename;
    }

    public function setImageFilename(?string $imageFilename): static
    {
        $this->imageFilename = $imageFilename;

        return $this;
    }

    public function getHost(): ?User
    {
        return $this->host;
    }

    public function setHost(?User $host): static
    {
        $this->host = $host;

        return $this;
    }

    public function isPrivate(): bool
    {
        return $this->private;
    }

    public function setPrivate(bool $private): static
    {
        $this->private = $private;

        return $this;
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

    public function isArchived(): bool
    {
        return $this->archived;
    }

    public function setArchived(bool $archived): static
    {
        $this->archived = $archived;

        return $this;
    }

    /** @return Collection<int, Post> */
    public function getPosts(): Collection
    {
        return $this->posts;
    }

    public function getTeam(): ?Team
    {
        return $this->team;
    }

    public function setTeam(Team $team): static
    {
        $this->team = $team;

        return $this;
    }
}
