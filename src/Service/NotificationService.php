<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Event;
use App\Entity\Notification;
use App\Entity\Post;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Enum\ParticipationStatus;
use App\Repository\FollowRepository;
use App\Repository\NotificationRepository;
use App\Repository\ParticipationRepository;
use App\Repository\TeamMemberRepository;
use Doctrine\ORM\EntityManagerInterface;

class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly NotificationRepository $notificationRepository,
        private readonly FollowRepository $followRepository,
        private readonly TeamMemberRepository $teamMemberRepository,
        private readonly ParticipationRepository $participationRepository,
    ) {
    }

    public function notifyEventStarted(Event $event): void
    {
        $recipients = [];

        $host = $event->getHost();
        if ($host !== null) {
            $recipients[$host->getId()] = $host;
        }

        $attendees = $this->participationRepository->findAttendeeUsers(
            $event,
            [ParticipationStatus::Going, ParticipationStatus::Maybe],
        );
        foreach ($attendees as $attendee) {
            $recipients[$attendee->getId()] = $attendee;
        }

        if ($recipients === []) {
            return;
        }

        foreach ($recipients as $recipient) {
            $n = new Notification($recipient, NotificationType::EventStarted);
            $n->setTargetEvent($event);
            $n->setActorTeam($event->getTeam());
            $this->em->persist($n);
        }

        $this->em->flush();
    }

    public function notifyTeamNewEvent(Event $event): void
    {
        $team = $event->getTeam();
        if ($team === null) {
            return;
        }

        $memberRows = $this->teamMemberRepository->findBy(['team' => $team]);
        $memberUsers = array_map(static fn ($m) => $m->getUser(), $memberRows);
        $followers = $this->followRepository->findUsersFollowingTeam($team);

        $host = $event->getHost();
        $hostId = $host?->getId();

        $byId = [];
        foreach (array_merge($memberUsers, $followers) as $u) {
            if ($u === null) {
                continue;
            }
            if ($hostId !== null && $u->getId() === $hostId) {
                continue;
            }
            $byId[$u->getId()] = $u;
        }

        foreach ($byId as $recipient) {
            $n = new Notification($recipient, NotificationType::TeamNewEvent);
            $n->setActorTeam($team);
            $n->setTargetEvent($event);
            $this->em->persist($n);
        }

        $this->em->flush();
    }

    public function notifyUserRsvpGoing(Event $event, User $actor): void
    {
        $followers = $this->followRepository->findUsersFollowingUser($actor);
        if ($followers === []) {
            return;
        }

        foreach ($followers as $recipient) {
            if ($recipient->getId() === $actor->getId()) {
                continue;
            }
            $n = new Notification($recipient, NotificationType::UserRsvpGoing);
            $n->setActorUser($actor);
            $n->setTargetEvent($event);
            $this->em->persist($n);
        }

        $this->em->flush();
    }

    public function notifyUserNewPost(Post $post): void
    {
        $author = $post->getAuthor();
        if ($author === null) {
            return;
        }

        $followers = $this->followRepository->findUsersFollowingUser($author);
        if ($followers === []) {
            return;
        }

        foreach ($followers as $recipient) {
            if ($recipient->getId() === $author->getId()) {
                continue;
            }
            $n = new Notification($recipient, NotificationType::UserNewPost);
            $n->setActorUser($author);
            $n->setTargetEvent($post->getEvent());
            $n->setTargetPost($post);
            $this->em->persist($n);
        }

        $this->em->flush();
    }

    public function markAllRead(User $recipient): void
    {
        $this->em->createQueryBuilder()
            ->update(Notification::class, 'n')
            ->set('n.readAt', ':now')
            ->andWhere('n.recipient = :user')
            ->andWhere('n.readAt IS NULL')
            ->setParameter('now', time())
            ->setParameter('user', $recipient)
            ->getQuery()
            ->execute();

        $this->em->clear();
    }

    public function delete(Notification $notification): void
    {
        $this->em->remove($notification);
        $this->em->flush();
    }

    public function clearAll(User $recipient): void
    {
        $this->em->createQueryBuilder()
            ->delete(Notification::class, 'n')
            ->andWhere('n.recipient = :user')
            ->setParameter('user', $recipient)
            ->getQuery()
            ->execute();
    }

    public function unreadCount(User $recipient): int
    {
        return $this->notificationRepository->unreadCountForUser($recipient);
    }
}
