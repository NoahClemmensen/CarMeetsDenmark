<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Follow;
use App\Entity\User;
use App\Service\FollowService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class FollowServiceTest extends KernelTestCase
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
    private FollowService $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->service = $container->get(FollowService::class);

        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    public function testFollowUserCreatesARow(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $this->em->flush();

        $follow = $this->service->followUser($a, $b);

        self::assertNotNull($follow->getId());
        self::assertSame($a->getId(), $follow->getFollower()->getId());
        self::assertSame($b->getId(), $follow->getTargetUser()->getId());
    }

    public function testFollowUserIsIdempotent(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $this->em->flush();

        $first = $this->service->followUser($a, $b);
        $second = $this->service->followUser($a, $b);

        self::assertSame($first->getId(), $second->getId());
    }

    public function testFollowingYourselfThrows(): void
    {
        $a = $this->makeUser();
        $this->em->flush();

        $this->expectException(\DomainException::class);
        $this->service->followUser($a, $a);
    }

    public function testUnfollowUserRemovesTheRow(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $this->em->flush();

        $this->service->followUser($a, $b);
        $this->service->unfollowUser($a, $b);

        self::assertNull(
            $this->em->getRepository(Follow::class)
                ->findOneBy(['follower' => $a, 'targetUser' => $b])
        );
    }

    public function testUnfollowUserIsNoOpWhenNotFollowing(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $this->em->flush();

        $this->service->unfollowUser($a, $b);

        self::assertNull(
            $this->em->getRepository(Follow::class)
                ->findOneBy(['follower' => $a, 'targetUser' => $b])
        );
    }

    public function testIsFollowingUserReflectsState(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        $this->em->flush();

        self::assertFalse($this->service->isFollowingUser($a, $b));
        $this->service->followUser($a, $b);
        self::assertTrue($this->service->isFollowingUser($a, $b));
    }

    public function testFollowTeamCreatesARow(): void
    {
        $a = $this->makeUser();
        $team = $this->makeTeam();
        $this->em->flush();

        $follow = $this->service->followTeam($a, $team);

        self::assertNotNull($follow->getId());
        self::assertSame($a->getId(), $follow->getFollower()->getId());
        self::assertSame($team->getId(), $follow->getTargetTeam()->getId());
    }

    public function testFollowTeamIsIdempotent(): void
    {
        $a = $this->makeUser();
        $team = $this->makeTeam();
        $this->em->flush();

        $first = $this->service->followTeam($a, $team);
        $second = $this->service->followTeam($a, $team);

        self::assertSame($first->getId(), $second->getId());
    }

    public function testUnfollowTeamRemovesTheRow(): void
    {
        $a = $this->makeUser();
        $team = $this->makeTeam();
        $this->em->flush();

        $this->service->followTeam($a, $team);
        $this->service->unfollowTeam($a, $team);

        self::assertNull(
            $this->em->getRepository(Follow::class)
                ->findOneBy(['follower' => $a, 'targetTeam' => $team])
        );
    }

    public function testIsFollowingTeamReflectsState(): void
    {
        $a = $this->makeUser();
        $team = $this->makeTeam();
        $this->em->flush();

        self::assertFalse($this->service->isFollowingTeam($a, $team));
        $this->service->followTeam($a, $team);
        self::assertTrue($this->service->isFollowingTeam($a, $team));
    }

    private function makeUser(): User
    {
        $u = new User();
        $u->setEmail('u' . uniqid('', true) . '@example.com');
        $u->setPassword('x');
        $this->em->persist($u);
        return $u;
    }

    private function makeTeam(): \App\Entity\Team
    {
        $team = new \App\Entity\Team();
        $team->setName('T');
        $team->setDescription('D');
        $this->em->persist($team);
        return $team;
    }
}
