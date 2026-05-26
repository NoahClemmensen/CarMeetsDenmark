<?php

namespace App\Repository;

use App\Entity\Event;
use App\Entity\Post;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Post>
 */
class PostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Post::class);
    }

    /**
     * Page 1: returns all pinned posts (ordered by pinnedAt DESC) + the first $limit unpinned posts (createdAt DESC).
     * Subsequent pages: pass $beforeCreatedAt = createdAt of the last visible unpinned post; returns the next $limit unpinned posts only.
     *
     * @return Post[]
     */
    public function findFeedPage(Event $event, int $limit, ?int $beforeCreatedAt = null): array
    {
        if ($beforeCreatedAt === null) {
            $pinned = $this->createQueryBuilder('p')
                ->andWhere('p.event = :event')
                ->andWhere('p.isDeleted = false')
                ->andWhere('p.pinnedAt IS NOT NULL')
                ->setParameter('event', $event)
                ->orderBy('p.pinnedAt', 'DESC')
                ->getQuery()
                ->getResult();

            $unpinned = $this->createQueryBuilder('p')
                ->andWhere('p.event = :event')
                ->andWhere('p.isDeleted = false')
                ->andWhere('p.pinnedAt IS NULL')
                ->setParameter('event', $event)
                ->orderBy('p.createdAt', 'DESC')
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();

            return array_merge($pinned, $unpinned);
        }

        return $this->createQueryBuilder('p')
            ->andWhere('p.event = :event')
            ->andWhere('p.isDeleted = false')
            ->andWhere('p.pinnedAt IS NULL')
            ->andWhere('p.createdAt < :before')
            ->setParameter('event', $event)
            ->setParameter('before', $beforeCreatedAt)
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findByUuid(string $uuid): ?Post
    {
        return $this->findOneBy(['uuid' => $uuid, 'isDeleted' => false]);
    }

    /**
     * Posts authored by $author that $viewer is allowed to see, newest first.
     * Excludes deleted posts and posts on deleted events. Private events are
     * only visible to their host (admins/support see everything).
     *
     * Cursor pagination: pass $beforeCreatedAt = createdAt of the last visible
     * post to fetch the next $limit posts.
     *
     * @return Post[]
     */
    public function findByAuthorVisibleTo(User $author, ?User $viewer, int $limit, ?int $beforeCreatedAt = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->innerJoin('p.event', 'e')
            ->andWhere('p.author = :author')
            ->andWhere('p.isDeleted = false')
            ->andWhere('e.isDeleted = false')
            ->setParameter('author', $author)
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit);

        $isPrivileged = $viewer !== null && ($viewer->isAdmin() || $viewer->isSupport());
        if (!$isPrivileged) {
            if ($viewer === null) {
                $qb->andWhere('e.private = false');
            } else {
                $qb->andWhere('e.private = false OR e.host = :viewer')
                    ->setParameter('viewer', $viewer);
            }
        }

        if ($beforeCreatedAt !== null) {
            $qb->andWhere('p.createdAt < :before')
                ->setParameter('before', $beforeCreatedAt);
        }

        return $qb->getQuery()->getResult();
    }
}
