<?php

namespace App\Repository;

use App\Entity\Event;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Event>
 */
class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    /**
     * Events the given user is allowed to see in listings.
     * Anonymous visitors (null user) see only public events.
     *
     * @return Event[]
     */
    public function findVisibleTo(?User $user): array
    {
        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.isDeleted = false')
            ->orderBy('e.id', 'DESC');

        if ($user === null) {
            $qb->andWhere('e.private = false');
        } else {
            $qb->andWhere('e.private = false OR e.host = :user')
                ->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }
}
