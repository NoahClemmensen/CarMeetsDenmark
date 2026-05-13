<?php

namespace App\Repository;

use App\Entity\Event;
use App\Entity\EventReaction;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EventReaction>
 */
class EventReactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EventReaction::class);
    }

    public function isHypedBy(Event $event, User $user): bool
    {
        return $this->findOneBy(['event' => $event, 'user' => $user]) !== null;
    }

    public function findOneForEventAndUser(Event $event, User $user): ?EventReaction
    {
        return $this->findOneBy(['event' => $event, 'user' => $user]);
    }
}
