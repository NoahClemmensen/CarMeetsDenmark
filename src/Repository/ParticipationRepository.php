<?php

namespace App\Repository;

use App\Entity\Event;
use App\Entity\Participation;
use App\Entity\User;
use App\Enum\ParticipationStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Participation>
 */
class ParticipationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Participation::class);
    }

    public function findForEventAndUser(Event $event, User $user): ?Participation
    {
        return $this->findOneBy(['event' => $event, 'user' => $user]);
    }

    /**
     * Returns counts keyed by status value, e.g. ['going' => 5, 'maybe' => 2, 'declined' => 1].
     * Missing statuses default to 0.
     *
     * @return array<string, int>
     */
    public function countsByStatusForEvent(Event $event): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('p.status AS status, COUNT(p.id) AS count')
            ->andWhere('p.event = :event')
            ->setParameter('event', $event)
            ->groupBy('p.status')
            ->getQuery()
            ->getArrayResult();

        $counts = array_fill_keys(array_map(fn (ParticipationStatus $s) => $s->value, ParticipationStatus::cases()), 0);
        foreach ($rows as $row) {
            $key = $row['status'] instanceof ParticipationStatus ? $row['status']->value : (string) $row['status'];
            $counts[$key] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Distinct users who hold one of the given statuses for the event.
     *
     * @param ParticipationStatus[] $statuses
     * @return User[]
     */
    public function findAttendeeUsers(Event $event, array $statuses): array
    {
        if ($statuses === []) {
            return [];
        }

        $participations = $this->createQueryBuilder('p')
            ->addSelect('u')
            ->join('p.user', 'u')
            ->andWhere('p.event = :event')
            ->andWhere('p.status IN (:statuses)')
            ->setParameter('event', $event)
            ->setParameter('statuses', array_map(static fn (ParticipationStatus $s) => $s->value, $statuses))
            ->getQuery()
            ->getResult();

        return array_map(static fn (Participation $p) => $p->getUser(), $participations);
    }
}
