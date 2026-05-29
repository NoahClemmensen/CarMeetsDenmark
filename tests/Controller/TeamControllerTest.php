<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Team;
use App\Entity\TeamMember;
use App\Entity\User;
use App\Enum\TeamRole;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class TeamControllerTest extends WebTestCase
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

    public function testCreateTeamMakesCreatorOwner(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->resetSchema($em);

        $user = $this->makeUser($em, ['ROLE_USER']);
        $em->flush();
        $client->loginUser($user);

        $crawler = $client->request('GET', '/teams/save');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Create team')->form();
        $form->setValues([
            'team[name]' => 'Audi Club DK',
            'team[description]' => 'Denmark Audi fans',
        ]);
        $client->submit($form);

        self::assertContains($client->getResponse()->getStatusCode(), [200, 302, 303]);

        $em->clear();
        $team = $em->getRepository(Team::class)->findOneBy(['name' => 'Audi Club DK']);
        self::assertNotNull($team);

        $membership = $em->getRepository(TeamMember::class)
            ->findOneBy(['team' => $team]);
        self::assertNotNull($membership);
        self::assertSame(TeamRole::Owner, $membership->getRole());
    }

    public function testInviteByEmailAddsMember(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->resetSchema($em);

        $owner = $this->makeUser($em, ['ROLE_USER']);
        $invitee = $this->makeUser($em, ['ROLE_USER']);
        $invitee->setEmail('invitee@example.com');
        $em->flush();

        $team = new Team();
        $team->setName('T');
        $team->setDescription('D');
        $em->persist($team);
        $em->persist(new TeamMember($team, $owner, TeamRole::Owner));
        $em->flush();

        $client->loginUser($owner);
        $crawler = $client->request('GET', '/teams/' . $team->getUuid() . '/invite-modal');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Add to team')->form();
        $form->setValues(['email' => 'invitee@example.com']);
        $client->submit($form);

        self::assertContains($client->getResponse()->getStatusCode(), [200, 302, 303]);

        $em->clear();
        $teamReloaded = $em->getRepository(Team::class)->find($team->getId());
        $inviteeReloaded = $em->getRepository(User::class)->findOneBy(['email' => 'invitee@example.com']);
        $member = $em->getRepository(TeamMember::class)
            ->findOneBy(['team' => $teamReloaded, 'user' => $inviteeReloaded]);
        self::assertNotNull($member);
        self::assertSame(TeamRole::Member, $member->getRole());
    }

    public function testInviteUnknownEmailDoesNotAddMember(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->resetSchema($em);

        $owner = $this->makeUser($em, ['ROLE_USER']);
        $em->flush();

        $team = new Team();
        $team->setName('T');
        $team->setDescription('D');
        $em->persist($team);
        $em->persist(new TeamMember($team, $owner, TeamRole::Owner));
        $em->flush();

        $client->loginUser($owner);
        $crawler = $client->request('GET', '/teams/' . $team->getUuid() . '/invite-modal');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Add to team')->form();
        $form->setValues(['email' => 'nobody@example.com']);
        $client->submit($form);

        self::assertSame(422, $client->getResponse()->getStatusCode());

        $em->clear();
        $count = $em->getRepository(TeamMember::class)
            ->count(['team' => $em->getRepository(Team::class)->find($team->getId())]);
        self::assertSame(1, $count, 'Only the owner should remain.');
    }

    public function testNonMemberCannotCreateEventForTeam(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->resetSchema($em);

        $owner = $this->makeUser($em, ['ROLE_USER']);
        $stranger = $this->makeUser($em, ['ROLE_USER']);
        $em->flush();

        $team = new Team();
        $team->setName('T');
        $team->setDescription('D');
        $em->persist($team);
        $em->persist(new TeamMember($team, $owner, TeamRole::Owner));
        $em->flush();

        $client->loginUser($stranger);
        $client->request('GET', '/event/save?team=' . $team->getUuid());

        self::assertContains($client->getResponse()->getStatusCode(), [403, 401]);
    }

    public function testOwnerCannotLeaveOwnTeam(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->resetSchema($em);

        $owner = $this->makeUser($em, ['ROLE_USER']);
        $em->flush();

        $team = new Team();
        $team->setName('T');
        $team->setDescription('D');
        $em->persist($team);
        $em->persist(new TeamMember($team, $owner, TeamRole::Owner));
        $em->flush();

        $client->loginUser($owner);
        // Owner is denied LEAVE by the voter (TeamVoter::LEAVE only grants to members).
        // The voter runs before CSRF validation, so any token value still produces 403.
        $client->request('POST', '/teams/' . $team->getUuid() . '/leave', [
            '_token' => 'irrelevant',
        ]);

        self::assertSame(403, $client->getResponse()->getStatusCode());
    }

    private function resetSchema(EntityManagerInterface $em): void
    {
        $schemaTool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    private function makeUser(EntityManagerInterface $em, array $roles): User
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user = new User();
        $user->setEmail('u' . uniqid('', true) . '@example.com');
        $user->setName('Test User');
        $user->setRoles($roles);
        $user->setPassword($hasher->hashPassword($user, 'password'));
        $em->persist($user);
        return $user;
    }

}
