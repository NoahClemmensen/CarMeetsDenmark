<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Event;
use App\Entity\Participation;
use App\Entity\User;
use App\Enum\ParticipationStatus;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class PostControllerTest extends WebTestCase
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


    public function testAnonymousCannotPost(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->resetSchema($em);

        $host = $this->makeUser($em, ['ROLE_USER']);
        $event = $this->makeEvent($em, $host);
        $em->flush();

        $client->request('POST', '/event/' . $event->getUuid() . '/post', ['post' => ['body' => 'hi']]);

        self::assertContains($client->getResponse()->getStatusCode(), [302, 403, 401]);
    }

    public function testPhotographerWithGoingParticipationCanPostTextOnly(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->resetSchema($em);

        $host = $this->makeUser($em, ['ROLE_USER']);
        $photographer = $this->makeUser($em, ['ROLE_USER', 'ROLE_PHOTOGRAPHER']);
        $event = $this->makeEvent($em, $host);
        $participation = new Participation($event, $photographer, ParticipationStatus::Going);
        $em->persist($participation);
        $em->flush();

        $client->loginUser($photographer);

        // Fetch the compose-modal route to pick up the form name and CSRF token.
        $crawler = $client->request('GET', '/event/' . $event->getUuid() . '/post/new');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Post')->form();
        $form->setValues(['post[body]' => 'Hello from a photographer']);
        $client->submit($form);

        self::assertTrue(
            in_array($client->getResponse()->getStatusCode(), [200, 303], true),
            'Got ' . $client->getResponse()->getStatusCode()
        );

        $em->clear();
        $repo = static::getContainer()->get(\App\Repository\PostRepository::class);
        $posts = $repo->findFeedPage($em->find(Event::class, $event->getId()), 20);

        self::assertCount(1, $posts);
        self::assertSame('Hello from a photographer', $posts[0]->getBody());
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

    private function makeEvent(EntityManagerInterface $em, User $host): Event
    {
        $event = new Event($host);
        $event->setName('Test Event');
        $event->setLocation('TestLoc');
        $event->setStartDate(new \DateTime('+1 day'));
        $event->setTimezone('UTC');
        $em->persist($event);
        return $event;
    }
}
