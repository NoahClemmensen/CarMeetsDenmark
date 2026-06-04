<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Event;
use App\Entity\Notification;
use App\Entity\Team;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Repository\FollowRepository;
use App\Repository\NotificationRepository;
use App\Repository\ParticipationRepository;
use App\Repository\TeamMemberRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class NotificationServiceEventStartedTest extends TestCase
{
    public function testNotifiesHostAndGoingMaybeAttendeesDeduped(): void
    {
        $host = $this->user(1);
        $going = $this->user(2);
        $maybe = $this->user(3);

        $event = new Event($host);
        $event->setTeam(new Team());

        $participation = $this->createMock(ParticipationRepository::class);
        // returns going + maybe attendees; host also happens to be an attendee (dedup case)
        $participation->method('findAttendeeUsers')->willReturn([$going, $maybe, $host]);

        $persisted = [];
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function (object $n) use (&$persisted): void {
            $persisted[] = $n;
        });

        $service = new NotificationService(
            $em,
            $this->createMock(NotificationRepository::class),
            $this->createMock(FollowRepository::class),
            $this->createMock(TeamMemberRepository::class),
            $participation,
        );

        $service->notifyEventStarted($event);

        self::assertCount(3, $persisted, 'host + going + maybe, deduped to 3');
        foreach ($persisted as $n) {
            self::assertInstanceOf(Notification::class, $n);
            self::assertSame(NotificationType::EventStarted, $n->getType());
            self::assertSame($event, $n->getTargetEvent());
        }
    }

    private function user(int $id): User
    {
        $user = new User();
        $user->setEmail("u{$id}@example.com");
        $user->setPassword('x');
        $ref = new \ReflectionProperty(User::class, 'id');
        $ref->setValue($user, $id);

        return $user;
    }
}
