<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Event;
use App\Entity\Notification;
use App\Entity\Post;
use App\Entity\Team;
use App\Entity\TeamMember;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Enum\TeamRole;
use App\Service\FollowService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class NotificationServiceTest extends KernelTestCase
{
    use \App\Tests\EnsuresSymfonyEnv;

    public static function setUpBeforeClass(): void
    {
        self::ensureSymfonyEnv();
    }

    protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }

    private EntityManagerInterface $em;
    private NotificationService $service;
    private FollowService $follow;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->service = $container->get(NotificationService::class);
        $this->follow = $container->get(FollowService::class);

        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    public function testNotifyTeamNewEventFansOutToMembersAndFollowers(): void
    {
        $host = $this->makeUser();
        $member = $this->makeUser();
        $follower = $this->makeUser();
        $bothMemberAndFollower = $this->makeUser();
        $outsider = $this->makeUser();
        $this->em->flush();

        $team = $this->makeTeam();
        $this->em->persist(new TeamMember($team, $host, TeamRole::Owner));
        $this->em->persist(new TeamMember($team, $member, TeamRole::Member));
        $this->em->persist(new TeamMember($team, $bothMemberAndFollower, TeamRole::Member));
        $this->em->flush();

        $this->follow->followTeam($follower, $team);
        $this->follow->followTeam($bothMemberAndFollower, $team);

        $event = $this->makeEvent($team, $host);
        $this->em->flush();

        $this->service->notifyTeamNewEvent($event);

        $rows = $this->em->getRepository(Notification::class)->findBy([], ['id' => 'ASC']);

        $recipients = array_map(static fn (Notification $n) => $n->getRecipient()->getId(), $rows);
        sort($recipients);
        $expected = [$member->getId(), $follower->getId(), $bothMemberAndFollower->getId()];
        sort($expected);
        self::assertSame($expected, $recipients);

        foreach ($rows as $row) {
            self::assertSame(NotificationType::TeamNewEvent, $row->getType());
            self::assertSame($team->getId(), $row->getActorTeam()->getId());
            self::assertNull($row->getActorUser());
            self::assertSame($event->getId(), $row->getTargetEvent()->getId());
            self::assertNull($row->getTargetPost());
        }
    }

    public function testNotifyUserRsvpGoingFansOutToFollowers(): void
    {
        $actor = $this->makeUser();
        $follower1 = $this->makeUser();
        $follower2 = $this->makeUser();
        $stranger = $this->makeUser();
        $this->em->flush();

        $this->follow->followUser($follower1, $actor);
        $this->follow->followUser($follower2, $actor);

        $team = $this->makeTeam();
        $event = $this->makeEvent($team, $this->makeUser());
        $this->em->flush();

        $this->service->notifyUserRsvpGoing($event, $actor);

        $rows = $this->em->getRepository(Notification::class)->findAll();
        $recipientIds = array_map(static fn (Notification $n) => $n->getRecipient()->getId(), $rows);
        sort($recipientIds);
        $expected = [$follower1->getId(), $follower2->getId()];
        sort($expected);
        self::assertSame($expected, $recipientIds);

        foreach ($rows as $row) {
            self::assertSame(NotificationType::UserRsvpGoing, $row->getType());
            self::assertSame($actor->getId(), $row->getActorUser()->getId());
            self::assertNull($row->getActorTeam());
            self::assertSame($event->getId(), $row->getTargetEvent()->getId());
            self::assertNull($row->getTargetPost());
        }
    }

    public function testNotifyUserNewPostFansOutToAuthorFollowers(): void
    {
        $author = $this->makeUser();
        $follower = $this->makeUser();
        $this->em->flush();

        $this->follow->followUser($follower, $author);

        $team = $this->makeTeam();
        $event = $this->makeEvent($team, $this->makeUser());
        $post = $this->makePost($event, $author);
        $this->em->flush();

        $this->service->notifyUserNewPost($post);

        $rows = $this->em->getRepository(Notification::class)->findAll();
        self::assertCount(1, $rows);
        self::assertSame($follower->getId(), $rows[0]->getRecipient()->getId());
        self::assertSame(NotificationType::UserNewPost, $rows[0]->getType());
        self::assertSame($author->getId(), $rows[0]->getActorUser()->getId());
        self::assertSame($event->getId(), $rows[0]->getTargetEvent()->getId());
        self::assertSame($post->getId(), $rows[0]->getTargetPost()->getId());
    }

    public function testMarkAllReadSetsReadAtOnlyForCurrentUserUnreadRows(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $this->em->flush();

        $team = $this->makeTeam();
        $event = $this->makeEvent($team, $this->makeUser());
        $this->em->flush();

        $this->makeNotificationFor($a, $team, $event);
        $alreadyRead = $this->makeNotificationFor($a, $team, $event);
        $alreadyRead->setReadAt(time() - 3600);
        $this->makeNotificationFor($b, $team, $event);
        $this->em->flush();

        $this->service->markAllRead($a);

        $allForA = $this->em->getRepository(Notification::class)
            ->findBy(['recipient' => $a]);
        foreach ($allForA as $n) {
            self::assertNotNull($n->getReadAt());
        }

        $allForB = $this->em->getRepository(Notification::class)
            ->findBy(['recipient' => $b]);
        self::assertNull($allForB[0]->getReadAt(), "B's notifications should remain unread.");
    }

    public function testDeleteRemovesOneRow(): void
    {
        $a = $this->makeUser();
        $this->em->flush();

        $team = $this->makeTeam();
        $event = $this->makeEvent($team, $this->makeUser());
        $this->em->flush();

        $n1 = $this->makeNotificationFor($a, $team, $event);
        $n2 = $this->makeNotificationFor($a, $team, $event);
        $this->em->flush();

        $this->service->delete($n1);

        $remaining = $this->em->getRepository(Notification::class)
            ->findBy(['recipient' => $a]);
        self::assertCount(1, $remaining);
        self::assertSame($n2->getId(), $remaining[0]->getId());
    }

    public function testClearAllRemovesAllForUser(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $this->em->flush();

        $team = $this->makeTeam();
        $event = $this->makeEvent($team, $this->makeUser());
        $this->em->flush();

        $this->makeNotificationFor($a, $team, $event);
        $this->makeNotificationFor($a, $team, $event);
        $this->makeNotificationFor($b, $team, $event);
        $this->em->flush();

        $this->service->clearAll($a);

        self::assertCount(0, $this->em->getRepository(Notification::class)->findBy(['recipient' => $a]));
        self::assertCount(1, $this->em->getRepository(Notification::class)->findBy(['recipient' => $b]));
    }

    public function testUnreadCount(): void
    {
        $a = $this->makeUser();
        $this->em->flush();

        $team = $this->makeTeam();
        $event = $this->makeEvent($team, $this->makeUser());
        $this->em->flush();

        $this->makeNotificationFor($a, $team, $event);
        $this->makeNotificationFor($a, $team, $event);
        $alreadyRead = $this->makeNotificationFor($a, $team, $event);
        $alreadyRead->setReadAt(time());
        $this->em->flush();

        self::assertSame(2, $this->service->unreadCount($a));
    }

    private function makeUser(): User
    {
        $u = new User();
        $u->setEmail('u' . uniqid('', true) . '@example.com');
        $u->setPassword('x');
        $this->em->persist($u);
        return $u;
    }

    private function makeTeam(): Team
    {
        $team = new Team();
        $team->setName('T');
        $team->setDescription('D');
        $this->em->persist($team);
        return $team;
    }

    private function makeEvent(Team $team, User $host): Event
    {
        $e = new Event($host);
        $e->setTeam($team);
        $e->setName('E');
        $e->setLocation('L');
        $e->setStartDate(new \DateTime('+1 day'));
        $e->setTimezone('UTC');
        $this->em->persist($e);
        return $e;
    }

    private function makePost(Event $event, User $author): Post
    {
        $p = new Post($event, $author);
        $p->setBody('hi');
        $this->em->persist($p);
        return $p;
    }

    private function makeNotificationFor(User $recipient, Team $team, Event $event): Notification
    {
        $n = new Notification($recipient, NotificationType::TeamNewEvent);
        $n->setActorTeam($team);
        $n->setTargetEvent($event);
        $this->em->persist($n);
        return $n;
    }
}
