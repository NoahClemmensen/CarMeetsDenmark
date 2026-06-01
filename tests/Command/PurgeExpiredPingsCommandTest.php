<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Entity\ActivityPing;
use App\Entity\User;
use App\Service\ActivityPingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class PurgeExpiredPingsCommandTest extends KernelTestCase
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

    public function testPurgeDeletesOnlyExpiredPings(): void
    {
        $expiredUser = $this->makeUser();
        $activeUser = $this->makeUser();
        $this->em->flush();

        $expired = $this->service->createPing($expiredUser, 55.0, 12.0);
        $this->backdate($expired, time() - ActivityPing::LIFETIME_SECONDS - 60);
        $this->service->createPing($activeUser, 56.0, 13.0);

        $tester = $this->runCommand();

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('Purged 1 expired', $tester->getDisplay());

        $this->em->clear();
        self::assertSame(1, $this->em->getRepository(ActivityPing::class)->count([]));
    }

    public function testPurgeIsNoOpWhenNothingExpired(): void
    {
        $user = $this->makeUser();
        $this->em->flush();
        $this->service->createPing($user, 55.0, 12.0);

        $tester = $this->runCommand();

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('Purged 0 expired', $tester->getDisplay());
        self::assertSame(1, $this->em->getRepository(ActivityPing::class)->count([]));
    }

    private function runCommand(): CommandTester
    {
        $application = new Application(self::$kernel);
        $command = $application->find('app:activity-ping:purge-expired');
        $tester = new CommandTester($command);
        $tester->execute([]);

        return $tester;
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
