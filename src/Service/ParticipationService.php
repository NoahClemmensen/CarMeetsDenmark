<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Event;
use App\Entity\Participation;
use App\Entity\User;
use App\Enum\ParticipationStatus;
use App\Repository\ParticipationRepository;
use Doctrine\ORM\EntityManagerInterface;

class ParticipationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ParticipationRepository $participationRepository,
    ) {
    }

    /**
     * Upsert a user's participation in an event. Creates the row on first call,
     * updates the status on subsequent calls.
     */
    public function setStatus(Event $event, User $user, ParticipationStatus $status): Participation
    {
        $participation = $this->participationRepository->findForEventAndUser($event, $user);

        if ($participation === null) {
            $participation = new Participation($event, $user, $status);
            $this->em->persist($participation);
        } else {
            $participation->setStatus($status);
        }

        $this->em->flush();

        return $participation;
    }

    public function getStatus(Event $event, User $user): ?ParticipationStatus
    {
        return $this->participationRepository->findForEventAndUser($event, $user)?->getStatus();
    }
}
