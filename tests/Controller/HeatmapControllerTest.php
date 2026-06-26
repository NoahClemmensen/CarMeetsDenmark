<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Service\ActivityPingService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class HeatmapControllerTest extends WebTestCase
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

    public function testAnonymousCanViewButNotPing(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->resetSchema($em);

        // Guests may view the read-only heatmap and its points...
        $client->request('GET', '/heatmap');
        self::assertResponseIsSuccessful();

        $client->request('GET', '/heatmap/points');
        self::assertResponseIsSuccessful();

        // ...but dropping a pin is an interaction and stays login-only.
        $client->request('POST', '/heatmap/ping', ['_token' => 'x', 'lat' => '55.0', 'lng' => '12.0']);
        self::assertContains($client->getResponse()->getStatusCode(), [302, 401, 403]);
    }

    public function testIndexPageRenders(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->resetSchema($em);

        $user = $this->makeUser($em, ['ROLE_USER']);
        $em->flush();

        $client->loginUser($user);
        $crawler = $client->request('GET', '/heatmap');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('[data-controller="heatmap"]'));
    }

    public function testPingHappyPathThroughRenderedCsrfToken(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->resetSchema($em);

        $user = $this->makeUser($em, ['ROLE_USER']);
        $em->flush();

        $client->loginUser($user);

        // Pull the real CSRF token the page rendered, same token the JS posts.
        $crawler = $client->request('GET', '/heatmap');
        $token = $crawler->filter('[data-heatmap-csrf-value]')->attr('data-heatmap-csrf-value');

        // Create
        $client->request('POST', '/heatmap/ping', ['_token' => $token, 'lat' => '55.6761', 'lng' => '12.5683']);
        self::assertResponseIsSuccessful();
        self::assertTrue(json_decode((string) $client->getResponse()->getContent(), true)['active']);

        $em->clear();
        self::assertSame(1, $em->getRepository(\App\Entity\ActivityPing::class)->count([]));

        // Remove (toggle off), no coordinates needed
        $client->request('POST', '/heatmap/ping', ['_token' => $token]);
        self::assertResponseIsSuccessful();
        self::assertFalse(json_decode((string) $client->getResponse()->getContent(), true)['active']);

        $em->clear();
        self::assertSame(0, $em->getRepository(\App\Entity\ActivityPing::class)->count([]));
    }

    public function testInvalidCsrfReturns403(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->resetSchema($em);

        $user = $this->makeUser($em, ['ROLE_USER']);
        $em->flush();

        $client->loginUser($user);
        $client->request('POST', '/heatmap/ping', ['_token' => 'invalid', 'lat' => '55.0', 'lng' => '12.0']);
        self::assertSame(403, $client->getResponse()->getStatusCode());
    }

    public function testPointsReturnsActivePings(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $this->resetSchema($em);

        $user = $this->makeUser($em, ['ROLE_USER']);
        $em->flush();

        $container->get(ActivityPingService::class)->createPing($user, 55.6761, 12.5683);

        $client->loginUser($user);
        $client->request('GET', '/heatmap/points');

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($data['points']);
        self::assertCount(1, $data['points']);
        self::assertEqualsWithDelta(55.6761, $data['points'][0][0], 0.0001);
        self::assertEqualsWithDelta(12.5683, $data['points'][0][1], 0.0001);
    }

    public function testPointsFiltersToBoundsAndReturnsCount(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $this->resetSchema($em);

        $service = $container->get(ActivityPingService::class);
        $copenhagen = $this->makeUser($em, ['ROLE_USER']);
        $aarhus = $this->makeUser($em, ['ROLE_USER']);
        $em->flush();
        // Copenhagen (inside the box) and Aarhus (outside it).
        $service->createPing($copenhagen, 55.6761, 12.5683);
        $service->createPing($aarhus, 56.1629, 10.2039);

        $client->request('GET', '/heatmap/points?minLat=55.5&maxLat=55.8&minLng=12.4&maxLng=12.7');

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertCount(1, $data['points']);
        self::assertSame(1, $data['count']);
        self::assertEqualsWithDelta(55.6761, $data['points'][0][0], 0.0001);
    }

    public function testPointsFallsBackToAllWhenBoundsInvalid(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $this->resetSchema($em);

        $service = $container->get(ActivityPingService::class);
        $copenhagen = $this->makeUser($em, ['ROLE_USER']);
        $aarhus = $this->makeUser($em, ['ROLE_USER']);
        $em->flush();
        $service->createPing($copenhagen, 55.6761, 12.5683);
        $service->createPing($aarhus, 56.1629, 10.2039);

        // minLat > maxLat is nonsensical: ignore bounds, return every active ping.
        $client->request('GET', '/heatmap/points?minLat=80&maxLat=10&minLng=12.4&maxLng=12.7');

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertCount(2, $data['points']);
        self::assertSame(2, $data['count']);
    }

    public function testPointsWithoutBoundsReturnsAllWithCount(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $this->resetSchema($em);

        $user = $this->makeUser($em, ['ROLE_USER']);
        $em->flush();
        $container->get(ActivityPingService::class)->createPing($user, 55.6761, 12.5683);

        $client->request('GET', '/heatmap/points');

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertCount(1, $data['points']);
        self::assertSame(1, $data['count']);
    }

    public function testPointsIsEmptyWhenNoPings(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->resetSchema($em);

        $user = $this->makeUser($em, ['ROLE_USER']);
        $em->flush();

        $client->loginUser($user);
        $client->request('GET', '/heatmap/points');

        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertSame([], $data['points']);
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
