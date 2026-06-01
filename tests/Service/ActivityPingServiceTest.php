<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\ActivityPing;
use App\Entity\User;
use App\Service\ActivityPingService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ActivityPingServiceTest extends KernelTestCase
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
    private ActivityPingService $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->service = $container->get(ActivityPingService::class);

        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    public function testCreatePingPersistsARow(): void
    {
        $user = $this->makeUser();
        $this->em->flush();

        $ping = $this->service->createPing($user, 55.6761, 12.5683);

        self::assertNotNull($ping->getId());
        self::assertSame($user->getId(), $ping->getUser()->getId());
        self::assertEqualsWithDelta(55.6761, $ping->getLat(), 0.0001);
        self::assertEqualsWithDelta(12.5683, $ping->getLng(), 0.0001);
        self::assertGreaterThan(time() - 5, $ping->getExpiresAt());
    }

    public function testExpiresAtIsThreeHoursAfterCreation(): void
    {
        $user = $this->makeUser();
        $this->em->flush();

        $ping = $this->service->createPing($user, 55.0, 12.0);

        self::assertSame($ping->getCreatedAt() + ActivityPing::LIFETIME_SECONDS, $ping->getExpiresAt());
    }

    public function testCreatePingWhenActiveExistsThrows(): void
    {
        $user = $this->makeUser();
        $this->em->flush();

        $this->service->createPing($user, 55.0, 12.0);

        $this->expectException(\DomainException::class);
        $this->service->createPing($user, 56.0, 13.0);
    }

    public function testCreatePingAfterRemoveSucceeds(): void
    {
        $user = $this->makeUser();
        $this->em->flush();

        $this->service->createPing($user, 55.0, 12.0);
        $this->service->removeActivePing($user);
        $second = $this->service->createPing($user, 56.0, 13.0);

        self::assertNotNull($second->getId());
    }

    public function testExpiredPingDoesNotBlockNewPing(): void
    {
        $user = $this->makeUser();
        $this->em->flush();

        $expired = $this->service->createPing($user, 55.0, 12.0);
        $this->backdate($expired, time() - ActivityPing::LIFETIME_SECONDS - 60);

        self::assertFalse($this->service->hasActivePing($user));
        $fresh = $this->service->createPing($user, 56.0, 13.0);
        self::assertNotNull($fresh->getId());
    }

    #[DataProvider('invalidCoordinatesProvider')]
    public function testInvalidCoordinatesThrow(float $lat, float $lng): void
    {
        $user = $this->makeUser();
        $this->em->flush();

        $this->expectException(\DomainException::class);
        $this->service->createPing($user, $lat, $lng);
    }

    /** @return iterable<string, array{0: float, 1: float}> */
    public static function invalidCoordinatesProvider(): iterable
    {
        yield 'lat too high' => [90.5, 12.0];
        yield 'lat too low' => [-90.5, 12.0];
        yield 'lng too high' => [55.0, 180.5];
        yield 'lng too low' => [55.0, -180.5];
    }

    public function testRemoveActivePingDeletesTheRow(): void
    {
        $user = $this->makeUser();
        $this->em->flush();

        $this->service->createPing($user, 55.0, 12.0);
        $this->service->removeActivePing($user);

        self::assertFalse($this->service->hasActivePing($user));
        self::assertNull($this->service->getActivePing($user));
    }

    public function testRemoveActivePingIsNoOpWhenNone(): void
    {
        $user = $this->makeUser();
        $this->em->flush();

        $this->service->removeActivePing($user);

        self::assertFalse($this->service->hasActivePing($user));
    }

    public function testGetActivePingReflectsState(): void
    {
        $user = $this->makeUser();
        $this->em->flush();

        self::assertNull($this->service->getActivePing($user));
        $ping = $this->service->createPing($user, 55.0, 12.0);
        self::assertSame($ping->getId(), $this->service->getActivePing($user)?->getId());
    }

    private function backdate(ActivityPing $ping, int $expiresAt): void
    {
        $ref = new \ReflectionProperty(ActivityPing::class, 'expiresAt');
        $ref->setValue($ping, $expiresAt);
        $this->em->flush();
    }

    private function makeUser(): User
    {
        $u = new User();
        $u->setEmail('u' . uniqid('', true) . '@example.com');
        $u->setPassword('x');
        $this->em->persist($u);
        return $u;
    }
}
