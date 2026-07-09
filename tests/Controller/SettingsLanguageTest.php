<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class SettingsLanguageTest extends WebTestCase
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

    public function testSettingsRendersDanishLabelForDanishUser(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->resetSchema($em);

        $user = $this->makeUser($em, 'da');
        $em->flush();
        $client->loginUser($user);

        $crawler = $client->request('GET', '/settings');

        self::assertResponseIsSuccessful();
        self::assertSame('da', $crawler->filter('html')->attr('lang'));
        self::assertStringContainsString('Sprog', $crawler->filter('label[for$="_language"]')->text());
    }

    public function testSettingsRendersEnglishLabelForEnglishUser(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->resetSchema($em);

        $user = $this->makeUser($em, 'en');
        $em->flush();
        $client->loginUser($user);

        $crawler = $client->request('GET', '/settings');

        self::assertResponseIsSuccessful();
        self::assertSame('en', $crawler->filter('html')->attr('lang'));
        self::assertStringContainsString('Language', $crawler->filter('label[for$="_language"]')->text());

        // Form labels and template strings resolve; no raw keys leak.
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Your name', $html);
        self::assertStringNotContainsString('form.profile', $html);
        self::assertStringNotContainsString('profile.about_you', $html);
    }

    private function resetSchema(EntityManagerInterface $em): void
    {
        $schemaTool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    private function makeUser(EntityManagerInterface $em, string $language): User
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $u = new User();
        $u->setEmail('u' . uniqid('', true) . '@example.com');
        $u->setName('Test');
        $u->setLanguage($language);
        $u->setRoles(['ROLE_USER']);
        $u->setPassword($hasher->hashPassword($u, 'password'));
        $em->persist($u);

        return $u;
    }
}
