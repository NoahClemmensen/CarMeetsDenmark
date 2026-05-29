<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\SaveEventDTO;
use App\Entity\Event;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class EventService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FileUploader $fileUploader,
        #[Autowire('%event_banners_directory%')]
        private readonly string $bannerDirectory,
        private readonly NotificationService $notificationService,
    ) {
    }

    public function saveFromDto(
        ?Event $event,
        SaveEventDTO $dto,
        ?UploadedFile $bannerFile,
        bool $removeBanner,
        User $author,
        ?\App\Entity\Team $team = null,
    ): Event {
        $isNew = $event === null;
        $event ??= new Event($author);
        if ($team !== null) {
            $event->setTeam($team);
        }

        $event->setName($dto->name);
        $event->setDescription($dto->description);
        $event->setStartDate($dto->startDate);
        $event->setEndDate($dto->endDate);
        $event->setLocation($dto->location);
        $event->setPrivate($dto->private);
        $event->setTimezone($dto->timezone ?? $author->getTimezone());
        $event->setRepeatFrequency($dto->repeatFrequency);
        $event->setRepeatAmount($dto->repeatAmount);

        // Removal runs before upload so checking "remove" + picking a new file still works.
        if ($removeBanner && $event->getImageFilename() !== null) {
            $this->fileUploader->remove($this->bannerDirectory, $event->getImageFilename());
            $event->setImageFilename(null);
        }

        if ($bannerFile !== null) {
            $newName = $this->fileUploader->upload(
                $bannerFile,
                $this->bannerDirectory,
                $event->getImageFilename(),
            );
            $event->setImageFilename($newName);
        }

        $this->em->persist($event);
        $this->em->flush();

        if ($isNew) {
            $this->notificationService->notifyTeamNewEvent($event);
        }

        return $event;
    }

    public function softDelete(Event $event): void
    {
        $event->setIsDeleted(true);
        $this->em->flush();
    }

}
