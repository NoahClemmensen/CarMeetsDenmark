<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Follow;
use App\Entity\Team;
use App\Entity\User;
use App\Repository\FollowRepository;
use Doctrine\ORM\EntityManagerInterface;

class FollowService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FollowRepository $followRepository,
    ) {
    }

    public function followUser(User $follower, User $target): Follow
    {
        if ($follower === $target || $follower->getId() === $target->getId()) {
            throw new \DomainException('You cannot follow yourself.');
        }
        $existing = $this->followRepository->findUserFollow($follower, $target);
        if ($existing !== null) {
            return $existing;
        }
        $follow = Follow::forUser($follower, $target);
        $this->em->persist($follow);
        $this->em->flush();
        return $follow;
    }

    public function unfollowUser(User $follower, User $target): void
    {
        $existing = $this->followRepository->findUserFollow($follower, $target);
        if ($existing === null) {
            return;
        }
        $this->em->remove($existing);
        $this->em->flush();
    }

    public function isFollowingUser(User $follower, User $target): bool
    {
        return $this->followRepository->findUserFollow($follower, $target) !== null;
    }

    public function followTeam(User $follower, Team $target): Follow
    {
        $existing = $this->followRepository->findTeamFollow($follower, $target);
        if ($existing !== null) {
            return $existing;
        }
        $follow = Follow::forTeam($follower, $target);
        $this->em->persist($follow);
        $this->em->flush();
        return $follow;
    }

    public function unfollowTeam(User $follower, Team $target): void
    {
        $existing = $this->followRepository->findTeamFollow($follower, $target);
        if ($existing === null) {
            return;
        }
        $this->em->remove($existing);
        $this->em->flush();
    }

    public function isFollowingTeam(User $follower, Team $target): bool
    {
        return $this->followRepository->findTeamFollow($follower, $target) !== null;
    }
}
