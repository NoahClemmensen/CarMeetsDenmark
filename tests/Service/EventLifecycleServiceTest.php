<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Event;
use App\Entity\Team;
use App\Entity\User;
use App\Enum\EventRepeatFrequency;
use App\Repository\EventRepository;
use App\Service\EventLifecycleService;
use App\Service\FileUploader;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class EventLifecycleServiceTest extends TestCase
{
    public function testNotifiesStartedEventOnceAndSetsMarker(): void
    {
        $now = new \DateTimeImmutable('2026-06-02 12:00:00');
        $event = $this->event(start: new \DateTime('2026-06-02 11:00:00'));

        $repo = $this->createMock(EventRepository::class);
        $repo->method('findPendingStartNotification')->willReturn([$event]);
        $repo->method('findArchivable')->willReturn([]);

        $notifier = $this->createMock(NotificationService::class);
        $notifier->expects(self::once())->method('notifyEventStarted')->with($event);

        $service = $this->service($repo, $notifier);
        $result = $service->processDue($now);

        self::assertSame($now->getTimestamp(), $event->getStartNotifiedAt());
        self::assertSame(1, $result['notified']);
    }

    public function testArchivesEventThatIsOver(): void
    {
        $event = $this->event(start: new \DateTime('2026-06-01 10:00:00'));

        $repo = $this->createMock(EventRepository::class);
        $repo->method('findPendingStartNotification')->willReturn([]);
        $repo->method('findArchivable')->willReturn([$event]);

        $service = $this->service($repo);
        $result = $service->processDue(new \DateTimeImmutable('2026-06-02 12:00:00'));

        self::assertTrue($event->isArchived());
        self::assertSame(1, $result['archived']);
        self::assertSame(0, $result['repeated']);
    }

    public function testRollsRepeatingEventForward(): void
    {
        $event = $this->event(start: new \DateTime('2026-06-01 18:00:00'));
        $event->setEndDate(new \DateTime('2026-06-01 22:00:00'));
        $event->setRepeatFrequency(EventRepeatFrequency::Weekly);
        $event->setRepeatAmount(2);

        $repo = $this->createMock(EventRepository::class);
        $repo->method('findPendingStartNotification')->willReturn([]);
        $repo->method('findArchivable')->willReturn([$event]);

        $persisted = [];
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function (object $e) use (&$persisted): void {
            $persisted[] = $e;
        });

        $service = $this->service($repo, em: $em);
        $result = $service->processDue(new \DateTimeImmutable('2026-06-02 12:00:00'));

        self::assertSame(1, $result['repeated']);
        self::assertCount(1, $persisted);

        /** @var Event $next */
        $next = $persisted[0];
        self::assertSame('2026-06-15 18:00:00', $next->getStartDate()->format('Y-m-d H:i:s'));
        self::assertSame('2026-06-15 22:00:00', $next->getEndDate()->format('Y-m-d H:i:s'));
        self::assertFalse($next->isArchived());
        self::assertNull($next->getStartNotifiedAt());
        self::assertSame(0, $next->getHypeCount());
    }

    public function testNonRepeatingEventIsNotCloned(): void
    {
        $event = $this->event(start: new \DateTime('2026-06-01 18:00:00'));

        $repo = $this->createMock(EventRepository::class);
        $repo->method('findPendingStartNotification')->willReturn([]);
        $repo->method('findArchivable')->willReturn([$event]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');

        $service = $this->service($repo, em: $em);
        $result = $service->processDue(new \DateTimeImmutable('2026-06-02 12:00:00'));

        self::assertSame(0, $result['repeated']);
    }

    private function service(
        EventRepository $repo,
        ?NotificationService $notifier = null,
        ?EntityManagerInterface $em = null,
    ): EventLifecycleService {
        return new EventLifecycleService(
            $em ?? $this->createMock(EntityManagerInterface::class),
            $repo,
            $notifier ?? $this->createMock(NotificationService::class),
            $this->createMock(FileUploader::class),
            '/tmp/banners',
        );
    }

    private function event(\DateTime $start): Event
    {
        $host = new User();
        $host->setEmail('h@example.com');
        $host->setPassword('x');

        $event = new Event($host);
        $event->setName('Cars & Coffee');
        $event->setLocation('Copenhagen');
        $event->setStartDate($start);
        $event->setTeam(new Team());

        return $event;
    }
}
