<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Dto\SaveEventDTO;
use App\Dto\SavePostDTO;
use App\Entity\Event;
use App\Entity\Notification;
use App\Entity\Team;
use App\Entity\TeamMember;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Enum\ParticipationStatus;
use App\Enum\TeamRole;
use App\Service\EventService;
use App\Service\FollowService;
use App\Service\ParticipationService;
use App\Service\PostService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class NotificationTriggersTest extends KernelTestCase
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
    private EventService $events;
    private ParticipationService $participation;
    private PostService $posts;
    private FollowService $follow;

    protected function setUp(): void
    {
        self::bootKernel();
        $c = static::getContainer();
        $this->em = $c->get(EntityManagerInterface::class);
        $this->events = $c->get(EventService::class);
        $this->participation = $c->get(ParticipationService::class);
        $this->posts = $c->get(PostService::class);
        $this->follow = $c->get(FollowService::class);

        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    public function testCreatingAnEventFansOutTeamNewEventNotifications(): void
    {
        $host = $this->makeUser();
        $member = $this->makeUser();
        $follower = $this->makeUser();
        $this->em->flush();

        $team = $this->makeTeam();
        $this->em->persist(new TeamMember($team, $host, TeamRole::Owner));
        $this->em->persist(new TeamMember($team, $member, TeamRole::Member));
        $this->em->flush();

        $this->follow->followTeam($follower, $team);

        $dto = new SaveEventDTO();
        $dto->name = 'E';
        $dto->location = 'L';
        $dto->startDate = new \DateTime('+1 day');
        $dto->timezone = 'UTC';

        $this->events->saveFromDto(null, $dto, null, false, $host, $team);

        $rows = $this->em->getRepository(Notification::class)->findBy(['type' => NotificationType::TeamNewEvent]);
        $recipients = array_map(static fn (Notification $n) => $n->getRecipient()->getId(), $rows);
        sort($recipients);
        $expected = [$member->getId(), $follower->getId()];
        sort($expected);
        self::assertSame($expected, $recipients, 'Host excluded; member and follower included.');
    }

    public function testRsvpGoingFirstTimeFiresUserRsvpGoing(): void
    {
        $host = $this->makeUser();
        $actor = $this->makeUser();
        $follower = $this->makeUser();
        $this->em->flush();

        $team = $this->makeTeam();
        $event = $this->makeEvent($team, $host);
        $this->em->flush();

        $this->follow->followUser($follower, $actor);

        $this->participation->setStatus($event, $actor, ParticipationStatus::Going);

        $rows = $this->em->getRepository(Notification::class)
            ->findBy(['type' => NotificationType::UserRsvpGoing]);
        self::assertCount(1, $rows);
        self::assertSame($follower->getId(), $rows[0]->getRecipient()->getId());
    }

    public function testRsvpGoingASecondTimeDoesNotFire(): void
    {
        $host = $this->makeUser();
        $actor = $this->makeUser();
        $follower = $this->makeUser();
        $this->em->flush();

        $team = $this->makeTeam();
        $event = $this->makeEvent($team, $host);
        $this->em->flush();

        $this->follow->followUser($follower, $actor);

        $this->participation->setStatus($event, $actor, ParticipationStatus::Going);
        $this->participation->setStatus($event, $actor, ParticipationStatus::Going);

        $rows = $this->em->getRepository(Notification::class)
            ->findBy(['type' => NotificationType::UserRsvpGoing]);
        self::assertCount(1, $rows);
    }

    public function testSwitchingFromGoingDoesNotFire(): void
    {
        $host = $this->makeUser();
        $actor = $this->makeUser();
        $follower = $this->makeUser();
        $this->em->flush();

        $team = $this->makeTeam();
        $event = $this->makeEvent($team, $host);
        $this->em->flush();

        $this->follow->followUser($follower, $actor);

        $this->participation->setStatus($event, $actor, ParticipationStatus::Going);
        $this->participation->setStatus($event, $actor, ParticipationStatus::Maybe);

        $rows = $this->em->getRepository(Notification::class)
            ->findBy(['type' => NotificationType::UserRsvpGoing]);
        self::assertCount(1, $rows, 'No new notification when leaving going.');
    }

    public function testCreatingAPostFiresUserNewPost(): void
    {
        $host = $this->makeUser();
        $author = $this->makeUser();
        $follower = $this->makeUser();
        $this->em->flush();

        $team = $this->makeTeam();
        $event = $this->makeEvent($team, $host);
        $this->em->flush();

        $this->follow->followUser($follower, $author);

        $dto = new SavePostDTO();
        $dto->body = 'hi';

        $this->posts->createFromDto($event, $author, $dto);

        $rows = $this->em->getRepository(Notification::class)
            ->findBy(['type' => NotificationType::UserNewPost]);
        self::assertCount(1, $rows);
        self::assertSame($follower->getId(), $rows[0]->getRecipient()->getId());
    }

    private function makeUser(): User
    {
        $u = new User();
        $u->setEmail('u' . uniqid('', true) . '@example.com');
        $u->setPassword('x');
        $u->setTimezone('UTC');
        $this->em->persist($u);
        return $u;
    }

    private function makeTeam(): Team
    {
        $t = new Team();
        $t->setName('T');
        $t->setDescription('D');
        $this->em->persist($t);
        return $t;
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
}
