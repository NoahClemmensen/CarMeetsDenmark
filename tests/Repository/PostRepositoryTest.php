<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Event;
use App\Entity\Post;
use App\Entity\User;
use App\Repository\PostRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PostRepositoryTest extends KernelTestCase
{
    use \App\Tests\EnsuresSymfonyEnv;

    public static function setUpBeforeClass(): void
    {
        self::ensureSymfonyEnv();
    }

    private EntityManagerInterface $em;
    private PostRepository $repo;

    protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }

    protected static function bootKernel(array $options = []): \Symfony\Component\HttpKernel\KernelInterface
    {
        $_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'test';
        $_SERVER['APP_DEBUG'] = $_ENV['APP_DEBUG'] = '1';

        if (method_exists(Dotenv::class, 'bootEnv')) {
            (new Dotenv())->bootEnv(dirname(__DIR__, 2).'/.env');
        }

        return parent::bootKernel($options);
    }

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->repo = $container->get(PostRepository::class);

        // Reset schema
        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    public function testReturnsPinnedFirstThenChronological(): void
    {
        $user = $this->makeUser();
        $event = $this->makeEvent($user);

        $oldUnpinned = $this->makePost($event, $user, createdAtOffset: -3600);
        $newUnpinned = $this->makePost($event, $user, createdAtOffset: -60);
        $pinnedOlder = $this->makePost($event, $user, createdAtOffset: -7200, pinnedAt: new DateTimeImmutable('-1 day'));
        $pinnedNewer = $this->makePost($event, $user, createdAtOffset: -7100, pinnedAt: new DateTimeImmutable('-1 hour'));

        $this->em->flush();

        $page = $this->repo->findFeedPage($event, limit: 20);

        self::assertCount(4, $page);
        // Pinned first, most-recently-pinned wins
        self::assertSame($pinnedNewer->getId(), $page[0]->getId());
        self::assertSame($pinnedOlder->getId(), $page[1]->getId());
        // Then unpinned, newest first
        self::assertSame($newUnpinned->getId(), $page[2]->getId());
        self::assertSame($oldUnpinned->getId(), $page[3]->getId());
    }

    public function testCursorPaginationExcludesPinnedAndOlderThanCursor(): void
    {
        $user = $this->makeUser();
        $event = $this->makeEvent($user);

        $now = time();
        $this->makePost($event, $user, createdAtOffset: 0, pinnedAt: new DateTimeImmutable());
        $page1Last = $this->makePost($event, $user, createdAtOffset: -100);
        $page2Item = $this->makePost($event, $user, createdAtOffset: -200);

        $this->em->flush();

        $page = $this->repo->findFeedPage($event, limit: 20, beforeCreatedAt: $page1Last->getCreatedAt());

        self::assertCount(1, $page);
        self::assertSame($page2Item->getId(), $page[0]->getId());
    }

    private function makeUser(): User
    {
        $user = new User();
        $user->setEmail('test+' . uniqid('', true) . '@example.com');
        $user->setPassword('x');
        $this->em->persist($user);
        return $user;
    }

    private function makeEvent(User $host): Event
    {
        $event = new Event($host);
        $event->setName('Test Event');
        $event->setLocation('Test Location');
        $event->setStartDate(new \DateTime('+1 day'));
        $event->setTimezone('UTC');
        $this->em->persist($event);
        return $event;
    }

    private function makePost(Event $event, User $author, int $createdAtOffset, ?DateTimeImmutable $pinnedAt = null): Post
    {
        $post = new Post($event, $author);
        $post->setBody('hello');
        $post->setPinnedAt($pinnedAt);
        // Override createdAt via reflection (it's set in constructor to time())
        $ref = new \ReflectionProperty(Post::class, 'createdAt');
        $ref->setValue($post, time() + $createdAtOffset);
        $this->em->persist($post);
        return $post;
    }
}
