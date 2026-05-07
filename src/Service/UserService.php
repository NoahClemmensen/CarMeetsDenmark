<?php

namespace App\Service;

use App\Dto\UserSetupDTO;
use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\ORM\EntityManagerInterface;

class UserService
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function updateFromUserSetup(User $user, UserSetupDTO $dto): void
    {
        $user->setName($dto->name);
        $user->setDescription($dto->description ?: null);

        $profileRoleValues = array_column(UserRole::cases(), 'value');
        $roles = array_values(array_filter($user->getRoles(), fn($r) => $r !== 'ROLE_USER' && !in_array($r, $profileRoleValues)));
        if ($dto->role !== null) {
            $roles[] = $dto->role->value;
        }
        $user->setRoles($roles);

        $user->setInstagramUrl($dto->instagramUrl ?: null);
        $user->setYoutubeUrl($dto->youtubeUrl ?: null);
        $user->setFacebookUrl($dto->facebookUrl ?: null);
        $user->setWebsiteUrl($dto->websiteUrl ?: null);
        $user->setTimezone($dto->timezone);
        $user->setLanguage($dto->language);

        $this->em->flush();
    }
}
