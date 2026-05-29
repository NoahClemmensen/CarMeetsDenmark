<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\SaveTeamDTO;
use App\Entity\Team;
use App\Entity\TeamMember;
use App\Entity\User;
use App\Enum\TeamRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class TeamService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FileUploader $fileUploader,
        #[Autowire('%team_banners_directory%')]
        private readonly string $bannerDirectory,
        #[Autowire('%team_profile_pictures_directory%')]
        private readonly string $profilePictureDirectory,
    ) {
    }

    public function createTeam(
        User $owner,
        SaveTeamDTO $dto,
        ?UploadedFile $bannerFile,
        ?UploadedFile $profilePictureFile,
    ): Team {
        $team = new Team();
        $team->setName($dto->name);
        $team->setDescription($dto->description);

        if ($bannerFile !== null) {
            $team->setBannerFilename($this->fileUploader->upload($bannerFile, $this->bannerDirectory));
        }
        if ($profilePictureFile !== null) {
            $team->setProfilePictureFilename($this->fileUploader->upload($profilePictureFile, $this->profilePictureDirectory));
        }

        $this->em->persist($team);

        $membership = new TeamMember($team, $owner, TeamRole::Owner);
        $this->em->persist($membership);

        $this->em->flush();

        return $team;
    }

    public function inviteMemberByEmail(Team $team, string $email): ?TeamMember
    {
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($user === null) {
            return null;
        }

        $existing = $this->em->getRepository(TeamMember::class)
            ->findOneBy(['team' => $team, 'user' => $user]);
        if ($existing !== null) {
            return $existing;
        }

        $member = new TeamMember($team, $user, TeamRole::Member);
        $this->em->persist($member);
        $this->em->flush();

        return $member;
    }

    public function removeMember(Team $team, User $user): void
    {
        $member = $this->em->getRepository(TeamMember::class)
            ->findOneBy(['team' => $team, 'user' => $user]);
        if ($member === null) {
            return;
        }
        if ($member->getRole() === TeamRole::Owner) {
            throw new \DomainException('Cannot remove the owner of a team.');
        }
        $this->em->remove($member);
        $this->em->flush();
    }

    public function leaveTeam(Team $team, User $user): void
    {
        $member = $this->em->getRepository(TeamMember::class)
            ->findOneBy(['team' => $team, 'user' => $user]);
        if ($member === null) {
            return;
        }
        if ($member->getRole() === TeamRole::Owner) {
            throw new \DomainException('Owner cannot leave their own team; delete it instead.');
        }
        $this->em->remove($member);
        $this->em->flush();
    }

    public function updateTeam(
        Team $team,
        SaveTeamDTO $dto,
        ?UploadedFile $bannerFile,
        ?UploadedFile $profilePictureFile,
        bool $removeBanner,
        bool $removeProfilePicture,
    ): Team {
        $team->setName($dto->name);
        $team->setDescription($dto->description);

        if ($removeBanner && $team->getBannerFilename() !== null) {
            $this->fileUploader->remove($this->bannerDirectory, $team->getBannerFilename());
            $team->setBannerFilename(null);
        }
        if ($bannerFile !== null) {
            $team->setBannerFilename($this->fileUploader->upload(
                $bannerFile,
                $this->bannerDirectory,
                $team->getBannerFilename(),
            ));
        }

        if ($removeProfilePicture && $team->getProfilePictureFilename() !== null) {
            $this->fileUploader->remove($this->profilePictureDirectory, $team->getProfilePictureFilename());
            $team->setProfilePictureFilename(null);
        }
        if ($profilePictureFile !== null) {
            $team->setProfilePictureFilename($this->fileUploader->upload(
                $profilePictureFile,
                $this->profilePictureDirectory,
                $team->getProfilePictureFilename(),
            ));
        }

        $this->em->flush();
        return $team;
    }

    public function deleteTeam(Team $team): void
    {
        $team->setIsDeleted(true);

        $events = $this->em->getRepository(\App\Entity\Event::class)
            ->findBy(['team' => $team, 'isDeleted' => false]);
        foreach ($events as $event) {
            $event->setIsDeleted(true);
        }

        $members = $this->em->getRepository(TeamMember::class)
            ->findBy(['team' => $team]);
        foreach ($members as $member) {
            $this->em->remove($member);
        }

        $this->em->flush();
    }
}
