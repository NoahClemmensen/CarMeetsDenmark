<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Event;
use App\Entity\EventReaction;
use App\Entity\Post;
use App\Entity\PostReaction;
use App\Entity\User;
use App\Repository\EventReactionRepository;
use App\Repository\PostReactionRepository;
use App\Service\Reaction\HypeResult;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

class ReactionService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PostReactionRepository $postReactions,
        private readonly EventReactionRepository $eventReactions,
    ) {
    }

    public function togglePostHype(Post $post, User $user): HypeResult
    {
        return $this->em->wrapInTransaction(function () use ($post, $user) {
            $this->em->lock($post, LockMode::PESSIMISTIC_WRITE);

            $existing = $this->postReactions->findOneForPostAndUser($post, $user);
            if ($existing !== null) {
                $this->em->remove($existing);
                $post->setHypeCount(max(0, $post->getHypeCount() - 1));
                $this->em->flush();
                return new HypeResult($post->getHypeCount(), false);
            }

            $reaction = new PostReaction($post, $user);
            $this->em->persist($reaction);
            $post->setHypeCount($post->getHypeCount() + 1);
            $this->em->flush();
            return new HypeResult($post->getHypeCount(), true);
        });
    }

    public function toggleEventHype(Event $event, User $user): HypeResult
    {
        return $this->em->wrapInTransaction(function () use ($event, $user) {
            $this->em->lock($event, LockMode::PESSIMISTIC_WRITE);

            $existing = $this->eventReactions->findOneForEventAndUser($event, $user);
            if ($existing !== null) {
                $this->em->remove($existing);
                $event->setHypeCount(max(0, $event->getHypeCount() - 1));
                $this->em->flush();
                return new HypeResult($event->getHypeCount(), false);
            }

            $reaction = new EventReaction($event, $user);
            $this->em->persist($reaction);
            $event->setHypeCount($event->getHypeCount() + 1);
            $this->em->flush();
            return new HypeResult($event->getHypeCount(), true);
        });
    }
}
