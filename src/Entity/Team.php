<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TeamRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: TeamRepository::class)]
class Team
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::GUID)]
    #[Groups(['public', 'sync'])]
    private ?string $uuid = null;

    #[ORM\Column(length: 120)]
    #[Groups(['public'])]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['public'])]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $bannerFilename = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $profilePictureFilename = null;

    #[ORM\Column]
    #[Groups(['public'])]
    private int $createdAt;

    #[ORM\Column(type: 'boolean')]
    private bool $isDeleted = false;

    /** @var Collection<int, TeamMember> */
    #[ORM\OneToMany(mappedBy: 'team', targetEntity: TeamMember::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $members;

    public function __construct()
    {
        $this->uuid = Uuid::v4()->toRfc4122();
        $this->createdAt = time();
        $this->members = new ArrayCollection();
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
    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getBannerFilename(): ?string
    {
        return $this->bannerFilename;
    }
    public function setBannerFilename(?string $filename): static
    {
        $this->bannerFilename = $filename;
        return $this;
    }

    public function getProfilePictureFilename(): ?string
    {
        return $this->profilePictureFilename;
    }
    public function setProfilePictureFilename(?string $filename): static
    {
        $this->profilePictureFilename = $filename;
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

    /** @return Collection<int, TeamMember> */
    public function getMembers(): Collection
    {
        return $this->members;
    }
}
