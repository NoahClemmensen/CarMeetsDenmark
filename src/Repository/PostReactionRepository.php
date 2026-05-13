<?php

namespace App\Repository;

use App\Entity\Post;
use App\Entity\PostReaction;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PostReaction>
 */
class PostReactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PostReaction::class);
    }

    /**
     * @param int[] $postIds
     * @return int[] subset of $postIds the user has reacted to
     */
    public function findPostIdsHypedBy(User $user, array $postIds): array
    {
        if ($postIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('r')
            ->select('IDENTITY(r.post) AS post_id')
            ->andWhere('r.user = :user')
            ->andWhere('r.post IN (:ids)')
            ->setParameter('user', $user)
            ->setParameter('ids', $postIds)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn(array $row) => (int) $row['post_id'], $rows);
    }

    public function findOneForPostAndUser(Post $post, User $user): ?PostReaction
    {
        return $this->findOneBy(['post' => $post, 'user' => $user]);
    }
}
