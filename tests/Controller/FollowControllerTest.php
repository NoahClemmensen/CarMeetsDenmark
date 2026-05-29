<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Follow;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class FollowControllerTest extends WebTestCase
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

    public function testToggleUserFollowOnAndOff(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->resetSchema($em);

        $follower = $this->makeUser($em, ['ROLE_USER']);
        $target = $this->makeUser($em, ['ROLE_USER']);
        $em->flush();

        $client->loginUser($follower);

        $crawler = $client->request('GET', '/user/' . $target->getUuid());
        self::assertResponseIsSuccessful();
        $form = $crawler->filter('form[action$="/follow/user/' . $target->getUuid() . '"]')->form();
        $client->submit($form);

        self::assertContains($client->getResponse()->getStatusCode(), [200, 302, 303]);

        $em->clear();
        $row = $em->getRepository(Follow::class)->findOneBy([
            'follower' => $em->getRepository(User::class)->find($follower->getId()),
            'targetUser' => $em->getRepository(User::class)->find($target->getId()),
        ]);
        self::assertNotNull($row, 'Follow row should exist after first toggle.');

        $crawler = $client->request('GET', '/user/' . $target->getUuid());
        $form = $crawler->filter('form[action$="/follow/user/' . $target->getUuid() . '"]')->form();
        $client->submit($form);

        $em->clear();
        $row = $em->getRepository(Follow::class)->findOneBy([
            'follower' => $em->getRepository(User::class)->find($follower->getId()),
            'targetUser' => $em->getRepository(User::class)->find($target->getId()),
        ]);
        self::assertNull($row, 'Follow row should be removed after second toggle.');
    }

    public function testSelfFollowReturns403(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->resetSchema($em);

        $user = $this->makeUser($em, ['ROLE_USER']);
        $em->flush();

        $client->loginUser($user);
        $client->request('POST', '/follow/user/' . $user->getUuid(), [
            '_token' => 'irrelevant',
        ]);
        self::assertSame(403, $client->getResponse()->getStatusCode());
    }

    public function testAnonymousIsRedirected(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->resetSchema($em);

        $target = $this->makeUser($em, ['ROLE_USER']);
        $em->flush();

        $client->request('POST', '/follow/user/' . $target->getUuid(), ['_token' => 'x']);
        self::assertContains($client->getResponse()->getStatusCode(), [302, 401, 403]);
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
}
