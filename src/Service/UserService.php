<?php

namespace App\Service;

use App\Dto\UserSetupDTO;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class UserService
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function updateFromUserSetup(User $user, UserSetupDTO $dto): void
    {
        $user->setName($dto->name);
        $user->setDescription($dto->description ?: null);
        $user->setProfileType($dto->profileType);
        $user->setInstagramUrl($dto->instagramUrl ?: null);
        $user->setYoutubeUrl($dto->youtubeUrl ?: null);
        $user->setFacebookUrl($dto->facebookUrl ?: null);
        $user->setWebsiteUrl($dto->websiteUrl ?: null);

        $this->em->flush();
    }
}
