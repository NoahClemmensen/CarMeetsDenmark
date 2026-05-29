<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Dto\SaveTeamDTO;
use App\Entity\Team;
use App\Entity\TeamMember;
use App\Entity\User;
use App\Enum\TeamRole;
use App\Service\TeamService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TeamServiceTest extends KernelTestCase
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
    private TeamService $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->service = $container->get(TeamService::class);

        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    public function testCreateTeamMakesCreatorOwner(): void
    {
        $user = $this->makeUser();
        $this->em->flush();

        $dto = new SaveTeamDTO();
        $dto->name = 'Audi Club DK';
        $dto->description = 'Denmark Audi fans.';

        $team = $this->service->createTeam($user, $dto, null, null);

        self::assertNotNull($team->getId());
        self::assertSame('Audi Club DK', $team->getName());

        $member = $this->em->getRepository(TeamMember::class)
            ->findOneBy(['team' => $team, 'user' => $user]);
        self::assertNotNull($member);
        self::assertSame(TeamRole::Owner, $member->getRole());
    }

    public function testInviteByEmailAddsExistingUserAsMember(): void
    {
        $owner = $this->makeUser();
        $invitee = $this->makeUser();
        $invitee->setEmail('invitee@example.com');
        $this->em->flush();

        $dto = new SaveTeamDTO();
        $dto->name = 'Team';
        $dto->description = 'Desc';
        $team = $this->service->createTeam($owner, $dto, null, null);

        $member = $this->service->inviteMemberByEmail($team, 'invitee@example.com');

        self::assertNotNull($member);
        self::assertSame($invitee->getId(), $member->getUser()->getId());
        self::assertSame(TeamRole::Member, $member->getRole());
    }

    public function testInviteByEmailReturnsNullForUnknownEmail(): void
    {
        $owner = $this->makeUser();
        $this->em->flush();

        $dto = new SaveTeamDTO();
        $dto->name = 'Team';
        $dto->description = 'Desc';
        $team = $this->service->createTeam($owner, $dto, null, null);

        $member = $this->service->inviteMemberByEmail($team, 'nobody@example.com');

        self::assertNull($member);
    }

    public function testInviteByEmailIsIdempotent(): void
    {
        $owner = $this->makeUser();
        $invitee = $this->makeUser();
        $invitee->setEmail('invitee@example.com');
        $this->em->flush();

        $dto = new SaveTeamDTO();
        $dto->name = 'Team';
        $dto->description = 'Desc';
        $team = $this->service->createTeam($owner, $dto, null, null);

        $first = $this->service->inviteMemberByEmail($team, 'invitee@example.com');
        $second = $this->service->inviteMemberByEmail($team, 'invitee@example.com');

        self::assertNotNull($first);
        self::assertSame($first->getId(), $second?->getId(), 'Repeat invite should return the existing membership.');
    }

    public function testRemoveMemberDetachesUserFromTeam(): void
    {
        $owner = $this->makeUser();
        $invitee = $this->makeUser();
        $invitee->setEmail('invitee@example.com');
        $this->em->flush();

        $dto = new SaveTeamDTO();
        $dto->name = 'T';
        $dto->description = 'D';
        $team = $this->service->createTeam($owner, $dto, null, null);
        $this->service->inviteMemberByEmail($team, 'invitee@example.com');

        $this->service->removeMember($team, $invitee);

        $still = $this->em->getRepository(TeamMember::class)
            ->findOneBy(['team' => $team, 'user' => $invitee]);
        self::assertNull($still);
    }

    public function testRemoveMemberRefusesToRemoveOwner(): void
    {
        $owner = $this->makeUser();
        $this->em->flush();

        $dto = new SaveTeamDTO();
        $dto->name = 'T';
        $dto->description = 'D';
        $team = $this->service->createTeam($owner, $dto, null, null);

        $this->expectException(\DomainException::class);
        $this->service->removeMember($team, $owner);
    }

    public function testLeaveTeamRemovesMembership(): void
    {
        $owner = $this->makeUser();
        $invitee = $this->makeUser();
        $invitee->setEmail('invitee@example.com');
        $this->em->flush();

        $dto = new SaveTeamDTO();
        $dto->name = 'T';
        $dto->description = 'D';
        $team = $this->service->createTeam($owner, $dto, null, null);
        $this->service->inviteMemberByEmail($team, 'invitee@example.com');

        $this->service->leaveTeam($team, $invitee);

        self::assertNull(
            $this->em->getRepository(TeamMember::class)
                ->findOneBy(['team' => $team, 'user' => $invitee])
        );
    }

    public function testOwnerCannotLeaveOwnTeam(): void
    {
        $owner = $this->makeUser();
        $this->em->flush();

        $dto = new SaveTeamDTO();
        $dto->name = 'T';
        $dto->description = 'D';
        $team = $this->service->createTeam($owner, $dto, null, null);

        $this->expectException(\DomainException::class);
        $this->service->leaveTeam($team, $owner);
    }

    public function testDeleteTeamSoftDeletesTeamAndEventsAndRemovesMembers(): void
    {
        $owner = $this->makeUser();
        $invitee = $this->makeUser();
        $invitee->setEmail('invitee@example.com');
        $this->em->flush();

        $dto = new SaveTeamDTO();
        $dto->name = 'T';
        $dto->description = 'D';
        $team = $this->service->createTeam($owner, $dto, null, null);
        $this->service->inviteMemberByEmail($team, 'invitee@example.com');
        $event = $this->makeEvent($team, $owner);
        $this->em->flush();

        $this->service->deleteTeam($team);

        $this->em->refresh($team);
        $this->em->refresh($event);

        self::assertTrue($team->isDeleted());
        self::assertTrue($event->isDeleted());

        $remaining = $this->em->getRepository(TeamMember::class)
            ->findBy(['team' => $team]);
        self::assertCount(0, $remaining);
    }

    public function testUpdateTeamChangesNameAndDescription(): void
    {
        $owner = $this->makeUser();
        $this->em->flush();

        $dto = new SaveTeamDTO();
        $dto->name = 'Old';
        $dto->description = 'Old desc';
        $team = $this->service->createTeam($owner, $dto, null, null);

        $update = new SaveTeamDTO();
        $update->name = 'New';
        $update->description = 'New desc';

        $this->service->updateTeam($team, $update, null, null, false, false);

        self::assertSame('New', $team->getName());
        self::assertSame('New desc', $team->getDescription());
    }

    private function makeUser(): User
    {
        $user = new User();
        $user->setEmail('u' . uniqid('', true) . '@example.com');
        $user->setPassword('x');
        $this->em->persist($user);
        return $user;
    }

    private function makeEvent(\App\Entity\Team $team, User $host): \App\Entity\Event
    {
        $event = new \App\Entity\Event($host);
        $event->setTeam($team);
        $event->setName('E');
        $event->setLocation('L');
        $event->setStartDate(new \DateTime('+1 day'));
        $event->setTimezone('UTC');
        $this->em->persist($event);
        return $event;
    }
}
