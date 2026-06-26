<?php

namespace App\Repository;

use App\Entity\ActivityPing;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActivityPing>
 */
class ActivityPingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityPing::class);
    }

    /**
     * Whether the user currently has a ping that has not yet expired.
     * Used to enforce the one-active-ping-at-a-time rule.
     */
    public function hasActivePing(User $user): bool
    {
        $count = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.user = :user')
            ->andWhere('p.expiresAt > :now')
            ->setParameter('user', $user)
            ->setParameter('now', time())
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }

    /**
     * The current user's active ping, or null if none.
     */
    public function findActiveForUser(User $user): ?ActivityPing
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.user = :user')
            ->andWhere('p.expiresAt > :now')
            ->setParameter('user', $user)
            ->setParameter('now', time())
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Anonymous coordinates of all active pings, for the heatmap overlay.
     * Returns a plain list of [lat, lng] float pairs, no ids, no users.
     *
     * @return array<int, array{0: float, 1: float}>
     */
    public function findActiveCoordinates(): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('p.lat AS lat, p.lng AS lng')
            ->andWhere('p.expiresAt > :now')
            ->setParameter('now', time())
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row) => [(float) $row['lat'], (float) $row['lng']], $rows);
    }

    /**
     * Anonymous coordinates of active pings within a lat/lng bounding box.
     * Boundaries are inclusive. Same plain [lat, lng] shape as
     * {@see self::findActiveCoordinates()}, for the viewport-scoped heatmap.
     *
     * @return array<int, array{0: float, 1: float}>
     */
    public function findActiveCoordinatesInBounds(
        float $minLat,
        float $maxLat,
        float $minLng,
        float $maxLng,
    ): array {
        $rows = $this->createQueryBuilder('p')
            ->select('p.lat AS lat, p.lng AS lng')
            ->andWhere('p.expiresAt > :now')
            ->andWhere('p.lat BETWEEN :minLat AND :maxLat')
            ->andWhere('p.lng BETWEEN :minLng AND :maxLng')
            ->setParameter('now', time())
            ->setParameter('minLat', $minLat)
            ->setParameter('maxLat', $maxLat)
            ->setParameter('minLng', $minLng)
            ->setParameter('maxLng', $maxLng)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row) => [(float) $row['lat'], (float) $row['lng']], $rows);
    }

    /**
     * Deletes all expired pings. Intended for a scheduled cleanup task.
     */
    public function deleteExpired(): int
    {
        return $this->createQueryBuilder('p')
            ->delete()
            ->andWhere('p.expiresAt <= :now')
            ->setParameter('now', time())
            ->getQuery()
            ->execute();
    }
}
