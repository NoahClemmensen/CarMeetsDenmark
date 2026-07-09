<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\User;
use App\Repository\ActivityPingRepository;
use App\Service\ActivityPingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Dotenv\Dotenv;

final class ActivityPingRepositoryTest extends KernelTestCase
{
    use \App\Tests\EnsuresSymfonyEnv;

    public static function setUpBeforeClass(): void
    {
        self::ensureSymfonyEnv();
    }

    private EntityManagerInterface $em;
    private ActivityPingRepository $repo;
    private ActivityPingService $service;

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
        $this->repo = $container->get(ActivityPingRepository::class);
        $this->service = $container->get(ActivityPingService::class);

        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    public function testReturnsOnlyPingsWithinBounds(): void
    {
        // Inside a Copenhagen-ish box.
        $this->pingAt(55.6761, 12.5683);
        // Outside it (Aarhus, well to the north-west).
        $this->pingAt(56.1629, 10.2039);

        $points = $this->repo->findActiveCoordinatesInBounds(55.5, 55.8, 12.4, 12.7);

        self::assertCount(1, $points);
        self::assertEqualsWithDelta(55.6761, $points[0][0], 0.0001);
        self::assertEqualsWithDelta(12.5683, $points[0][1], 0.0001);
    }

    public function testIncludesPingsExactlyOnBoundaryAndExcludesExpired(): void
    {
        $this->pingAt(55.5, 12.4); // exactly on the min corner
        $this->pingAt(55.8, 12.7); // exactly on the max corner

        $points = $this->repo->findActiveCoordinatesInBounds(55.5, 55.8, 12.4, 12.7);

        self::assertCount(2, $points);
    }

    private function pingAt(float $lat, float $lng): void
    {
        // Each ping needs its own user (one-active-ping-per-user rule).
        $user = new User();
        $user->setEmail('u' . uniqid('', true) . '@example.com');
        $user->setName('Test');
        $user->setRoles(['ROLE_USER']);
        $user->setPassword('x');
        $this->em->persist($user);
        $this->em->flush();

        $this->service->createPing($user, $lat, $lng);
    }
}
