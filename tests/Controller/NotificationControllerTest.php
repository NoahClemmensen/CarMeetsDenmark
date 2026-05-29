<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Event;
use App\Entity\Notification;
use App\Entity\Team;
use App\Entity\User;
use App\Enum\NotificationType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class NotificationControllerTest extends WebTestCase
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

    public function testDropdownReturns200AndMarksAllRead(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->resetSchema($em);

        $user = $this->makeUser($em, ['ROLE_USER']);
        $other = $this->makeUser($em, ['ROLE_USER']);
        $team = $this->makeTeam($em);
        $event = $this->makeEvent($em, $team, $other);
        $em->flush();

        $n = new Notification($user, NotificationType::TeamNewEvent);
        $n->setActorTeam($team);
        $n->setTargetEvent($event);
        $em->persist($n);
        $em->flush();
        $nId = $n->getId();

        $client->loginUser($user);
        $client->request('GET', '/notifications/dropdown');
        self::assertResponseIsSuccessful();

        $em->clear();
        $reloaded = $em->getRepository(Notification::class)->find($nId);
        self::assertNotNull($reloaded->getReadAt(), 'Opening the dropdown should mark unread rows read.');
    }

    public function testDeleteRemovesOwnNotification(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->resetSchema($em);

        $user = $this->makeUser($em, ['ROLE_USER']);
        $team = $this->makeTeam($em);
        $event = $this->makeEvent($em, $team, $this->makeUser($em, ['ROLE_USER']));
        $em->flush();

        $n = new Notification($user, NotificationType::TeamNewEvent);
        $n->setActorTeam($team);
        $n->setTargetEvent($event);
        $em->persist($n);
        $em->flush();
        $nId = $n->getId();

        $client->loginUser($user);

        $crawler = $client->request('GET', '/notifications/dropdown');
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form[action$="/notifications/' . $n->getUuid() . '/delete"]')->form();
        $client->submit($form);

        self::assertContains($client->getResponse()->getStatusCode(), [200, 303]);

        $em->clear();
        self::assertNull($em->getRepository(Notification::class)->find($nId));
    }

    public function testCannotDeleteOthersNotification(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->resetSchema($em);

        $owner = $this->makeUser($em, ['ROLE_USER']);
        $intruder = $this->makeUser($em, ['ROLE_USER']);
        $team = $this->makeTeam($em);
        $event = $this->makeEvent($em, $team, $this->makeUser($em, ['ROLE_USER']));
        $em->flush();

        $n = new Notification($owner, NotificationType::TeamNewEvent);
        $n->setActorTeam($team);
        $n->setTargetEvent($event);
        $em->persist($n);
        $em->flush();

        $client->loginUser($intruder);
        $client->request('POST', '/notifications/' . $n->getUuid() . '/delete', ['_token' => 'x']);
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
        $u = new User();
        $u->setEmail('u' . uniqid('', true) . '@example.com');
        $u->setName('Test');
        $u->setRoles($roles);
        $u->setPassword($hasher->hashPassword($u, 'password'));
        $em->persist($u);
        return $u;
    }

    private function makeTeam(EntityManagerInterface $em): Team
    {
        $team = new Team();
        $team->setName('T');
        $team->setDescription('D');
        $em->persist($team);
        return $team;
    }

    private function makeEvent(EntityManagerInterface $em, Team $team, User $host): Event
    {
        $e = new Event($host);
        $e->setTeam($team);
        $e->setName('E');
        $e->setLocation('L');
        $e->setStartDate(new \DateTime('+1 day'));
        $e->setTimezone('UTC');
        $em->persist($e);
        return $e;
    }
}
