<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ActivityPing;
use App\Entity\User;
use App\Repository\ActivityPingRepository;
use Doctrine\ORM\EntityManagerInterface;

class ActivityPingService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ActivityPingRepository $pingRepository,
    ) {
    }

    /**
     * Creates an activity ping at the given coordinates for the user.
     *
     * @throws \DomainException if the coordinates are out of range or the user
     *                          already has an active ping.
     */
    public function createPing(User $user, float $lat, float $lng): ActivityPing
    {
        if ($lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0) {
            throw new \DomainException('Coordinates are out of range.');
        }
        if ($this->pingRepository->hasActivePing($user)) {
            throw new \DomainException('You already have an active ping.');
        }

        $ping = new ActivityPing($user, $lat, $lng);
        $this->em->persist($ping);
        $this->em->flush();

        return $ping;
    }

    /**
     * Removes the user's active ping, if any. No-op when none exists.
     */
    public function removeActivePing(User $user): void
    {
        $ping = $this->pingRepository->findActiveForUser($user);
        if ($ping === null) {
            return;
        }
        $this->em->remove($ping);
        $this->em->flush();
    }

    public function getActivePing(User $user): ?ActivityPing
    {
        return $this->pingRepository->findActiveForUser($user);
    }

    public function hasActivePing(User $user): bool
    {
        return $this->pingRepository->hasActivePing($user);
    }
}
