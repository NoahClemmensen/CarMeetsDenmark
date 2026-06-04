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

    /**
     * @return Event[]
     */
    public function findUpcomingForTeam(\App\Entity\Team $team, bool $includePrivate): array
    {
        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.team = :team')
            ->andWhere('e.isDeleted = false')
            ->andWhere('e.startDate >= :now')
            ->orderBy('e.startDate', 'ASC')
            ->setParameter('team', $team)
            ->setParameter('now', new \DateTimeImmutable());

        if (!$includePrivate) {
            $qb->andWhere('e.private = false');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Event[]
     */
    public function findPastForTeam(\App\Entity\Team $team, bool $includePrivate): array
    {
        $qb = $this->createQueryBuilder('e')
            ->andWhere('e.team = :team')
            ->andWhere('e.isDeleted = false')
            ->andWhere('e.startDate < :now')
            ->orderBy('e.startDate', 'DESC')
            ->setParameter('team', $team)
            ->setParameter('now', new \DateTimeImmutable());

        if (!$includePrivate) {
            $qb->andWhere('e.private = false');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Events whose start time has passed but which have not yet sent their
     * "happening now" notification. Excludes deleted/archived events.
     *
     * @return Event[]
     */
    public function findPendingStartNotification(\DateTimeImmutable $now): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.isDeleted = false')
            ->andWhere('e.archived = false')
            ->andWhere('e.startNotifiedAt IS NULL')
            ->andWhere('e.startDate <= :now')
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();
    }

    /**
     * Events that are over and should be archived: endDate has passed, or
     * (when no endDate is set) startDate has passed. Excludes deleted/archived.
     *
     * @return Event[]
     */
    public function findArchivable(\DateTimeImmutable $now): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.isDeleted = false')
            ->andWhere('e.archived = false')
            ->andWhere('(e.endDate IS NOT NULL AND e.endDate <= :now) OR (e.endDate IS NULL AND e.startDate <= :now)')
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();
    }
}
