<?php

namespace App\Repository;

use App\Entity\Event;
use App\Entity\Participation;
use App\Entity\TeamMember;
use App\Entity\User;
use App\Enum\ParticipationStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
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

    /**
     * Upcoming events the user has RSVP'd Going or Maybe to. Powers the
     * "Attending" section of the My events page.
     *
     * @return Event[]
     */
    public function findUpcomingAttendingFor(User $user, \DateTimeImmutable $now): array
    {
        return $this->createQueryBuilder('e')
            ->innerJoin(
                Participation::class,
                'p',
                Join::WITH,
                'p.event = e AND p.user = :user AND p.status IN (:statuses)',
            )
            ->andWhere('e.isDeleted = false')
            ->andWhere('e.archived = false')
            ->andWhere('e.startDate >= :now')
            ->orderBy('e.startDate', 'ASC')
            ->setParameter('user', $user)
            ->setParameter('statuses', [
                ParticipationStatus::Going->value,
                ParticipationStatus::Maybe->value,
            ])
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();
    }

    /**
     * Upcoming events belonging to teams the user is a member of (this includes
     * events they host). Powers the "From your teams" section; pass the ids of
     * events already shown under "Attending" to avoid listing them twice.
     *
     * @param int[] $excludeEventIds
     * @return Event[]
     */
    public function findUpcomingForUserTeams(User $user, array $excludeEventIds, \DateTimeImmutable $now): array
    {
        $qb = $this->createQueryBuilder('e')
            ->innerJoin(TeamMember::class, 'tm', Join::WITH, 'tm.team = e.team AND tm.user = :user')
            ->andWhere('e.isDeleted = false')
            ->andWhere('e.archived = false')
            ->andWhere('e.startDate >= :now')
            ->orderBy('e.startDate', 'ASC')
            ->setParameter('user', $user)
            ->setParameter('now', $now);

        if ($excludeEventIds !== []) {
            $qb->andWhere('e.id NOT IN (:exclude)')
                ->setParameter('exclude', $excludeEventIds);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * One page of Discover results: upcoming events the user is allowed to see
     * and has not already responded to or hosts. Returns the events plus the
     * total matching count (for pagination).
     *
     * @return array{events: Event[], total: int}
     */
    public function findDiscoverPage(?User $user, string $sort, ?string $search, int $page, int $perPage): array
    {
        $now = new \DateTimeImmutable();

        $qb = $this->createQueryBuilder('e')
            // HIDDEN keeps hydration as plain Event entities while still allowing
            // ORDER BY on the going-participation count for the "attendees" sort.
            ->select('e', 'COUNT(DISTINCT pg.id) AS HIDDEN goingCount')
            ->leftJoin(
                Participation::class,
                'pg',
                Join::WITH,
                'pg.event = e AND pg.status = :goingStatus',
            )
            ->groupBy('e.id')
            ->setParameter('goingStatus', ParticipationStatus::Going->value);
        $this->applyDiscoverFilters($qb, $user, $search, $now);

        match ($sort) {
            'hype' => $qb->orderBy('e.hypeCount', 'DESC')->addOrderBy('e.startDate', 'ASC'),
            'attendees' => $qb->orderBy('goingCount', 'DESC')->addOrderBy('e.startDate', 'ASC'),
            default => $qb->orderBy('e.startDate', 'ASC'),
        };

        $events = $qb
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return ['events' => $events, 'total' => $this->countDiscover($user, $search, $now)];
    }

    private function countDiscover(?User $user, ?string $search, \DateTimeImmutable $now): int
    {
        $qb = $this->createQueryBuilder('e')->select('COUNT(DISTINCT e.id)');
        $this->applyDiscoverFilters($qb, $user, $search, $now);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Shared WHERE clause for Discover listing and its count. Anonymous viewers
     * see only public, upcoming events; signed-in viewers additionally see
     * private events from their own teams, but never events they already
     * responded to or host (those belong on the My events page).
     */
    private function applyDiscoverFilters(QueryBuilder $qb, ?User $user, ?string $search, \DateTimeImmutable $now): void
    {
        $qb->andWhere('e.isDeleted = false')
            ->andWhere('e.archived = false')
            ->andWhere('e.startDate >= :now')
            ->setParameter('now', $now);

        if ($user === null) {
            $qb->andWhere('e.private = false');
        } else {
            $qb->andWhere(
                '(e.private = false OR EXISTS ('
                . 'SELECT tmv.id FROM ' . TeamMember::class . ' tmv WHERE tmv.team = e.team AND tmv.user = :viewer'
                . '))',
            )
                ->andWhere(
                    'NOT EXISTS ('
                    . 'SELECT pmine.id FROM ' . Participation::class . ' pmine WHERE pmine.event = e AND pmine.user = :viewer'
                    . ')',
                )
                ->andWhere('(e.host IS NULL OR e.host != :viewer)')
                ->setParameter('viewer', $user);
        }

        if ($search !== null && $search !== '') {
            $qb->andWhere('(e.name LIKE :q OR e.location LIKE :q OR e.description LIKE :q)')
                ->setParameter('q', '%' . $search . '%');
        }
    }

    /**
     * Going-participation counts keyed by event id, for the given events.
     * Events with no "going" RSVPs are simply absent from the map.
     *
     * @param Event[] $events
     * @return array<int, int>
     */
    public function goingCountsForEvents(array $events): array
    {
        if ($events === []) {
            return [];
        }

        $ids = array_map(static fn (Event $e) => $e->getId(), $events);

        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(p.event) AS eventId, COUNT(p.id) AS cnt')
            ->from(Participation::class, 'p')
            ->andWhere('p.event IN (:ids)')
            ->andWhere('p.status = :going')
            ->groupBy('p.event')
            ->setParameter('ids', $ids)
            ->setParameter('going', ParticipationStatus::Going->value)
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['eventId']] = (int) $row['cnt'];
        }

        return $counts;
    }
}
