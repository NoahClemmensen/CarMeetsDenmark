<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Event;
use App\Entity\Post;
use App\Entity\User;
use App\Service\ReactionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ReactionServiceTest extends KernelTestCase
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
    private ReactionService $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->service = $container->get(ReactionService::class);

        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    public function testTogglePostHypeOnThenOff(): void
    {
        $user = $this->makeUser();
        $event = $this->makeEvent($user);
        $post = $this->makePost($event, $user);
        $this->em->flush();

        $result1 = $this->service->togglePostHype($post, $user);
        self::assertTrue($result1->isHyped);
        self::assertSame(1, $result1->newCount);

        $this->em->refresh($post);
        self::assertSame(1, $post->getHypeCount());

        $result2 = $this->service->togglePostHype($post, $user);
        self::assertFalse($result2->isHyped);
        self::assertSame(0, $result2->newCount);

        $this->em->refresh($post);
        self::assertSame(0, $post->getHypeCount());
    }

    public function testToggleEventHypeOnThenOff(): void
    {
        $user = $this->makeUser();
        $event = $this->makeEvent($user);
        $this->em->flush();

        $r1 = $this->service->toggleEventHype($event, $user);
        self::assertTrue($r1->isHyped);
        self::assertSame(1, $r1->newCount);

        $r2 = $this->service->toggleEventHype($event, $user);
        self::assertFalse($r2->isHyped);
        self::assertSame(0, $r2->newCount);
    }

    private function makeUser(): User
    {
        $user = new User();
        $user->setEmail('u' . uniqid('', true) . '@example.com');
        $user->setPassword('x');
        $this->em->persist($user);
        return $user;
    }

    private function makeEvent(User $host): Event
    {
        $team = new \App\Entity\Team();
        $team->setName('T');
        $team->setDescription('D');
        $this->em->persist($team);

        $event = new Event($host);
        $event->setTeam($team);
        $event->setName('E');
        $event->setLocation('L');
        $event->setStartDate(new \DateTime('+1 day'));
        $event->setTimezone('UTC');
        $this->em->persist($event);
        return $event;
    }

    private function makePost(Event $event, User $author): Post
    {
        $post = new Post($event, $author);
        $post->setBody('hi');
        $this->em->persist($post);
        return $post;
    }
}
