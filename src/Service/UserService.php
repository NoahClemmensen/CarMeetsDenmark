<?php

namespace App\Service;

use App\Dto\UserSetupDTO;
use App\Entity\User;
use App\Enum\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class UserService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FileUploader $fileUploader,
        #[Autowire('%user_avatars_directory%')]
        private readonly string $avatarDirectory,
    ) {
    }

    /**
     * Apply a setup-form DTO to a User and persist.
     *
     * The role-merging step is the subtle part: we strip the implicit
     * `ROLE_USER` and any existing profile role from {@see UserRole}, then
     * append the newly-chosen profile role. Anything else on the user
     * (e.g. `ROLE_SUPPORT`, `ROLE_ADMIN`) is preserved.
     */
    public function updateFromUserSetup(
        User $user,
        UserSetupDTO $dto,
        ?UploadedFile $avatarFile = null,
        bool $removeAvatar = false,
    ): void {
        $user->setName($dto->name);
        $user->setDescription($dto->description ?: null);

        $profileRoleValues = array_column(UserRole::cases(), 'value');
        $roles = array_values(array_filter($user->getRoles(), fn ($r) => $r !== 'ROLE_USER' && !in_array($r, $profileRoleValues)));
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

        // Removal runs before upload so checking "remove" + picking a new file still works.
        if ($removeAvatar && $user->getAvatarFilename() !== null) {
            $this->fileUploader->remove($this->avatarDirectory, $user->getAvatarFilename());
            $user->setAvatarFilename(null);
        }

        if ($avatarFile !== null) {
            $newName = $this->fileUploader->upload(
                $avatarFile,
                $this->avatarDirectory,
                $user->getAvatarFilename(),
            );
            $user->setAvatarFilename($newName);
        }

        $this->em->flush();
    }
}
