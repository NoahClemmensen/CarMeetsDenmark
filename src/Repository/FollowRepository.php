<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Follow;
use App\Entity\Team;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Follow>
 */
class FollowRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Follow::class);
    }

    public function findUserFollow(User $follower, User $target): ?Follow
    {
        return $this->findOneBy(['follower' => $follower, 'targetUser' => $target]);
    }

    public function findTeamFollow(User $follower, Team $target): ?Follow
    {
        return $this->findOneBy(['follower' => $follower, 'targetTeam' => $target]);
    }

    /**
     * @return User[]
     */
    public function findUsersFollowingTeam(Team $team): array
    {
        $rows = $this->createQueryBuilder('f')
            ->andWhere('f.targetTeam = :team')
            ->setParameter('team', $team)
            ->getQuery()
            ->getResult();

        return array_map(static fn (Follow $f) => $f->getFollower(), $rows);
    }

    /**
     * @return User[]
     */
    public function findUsersFollowingUser(User $user): array
    {
        $rows = $this->createQueryBuilder('f')
            ->andWhere('f.targetUser = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        return array_map(static fn (Follow $f) => $f->getFollower(), $rows);
    }
}
