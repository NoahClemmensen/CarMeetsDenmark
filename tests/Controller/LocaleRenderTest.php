<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\EnsuresSymfonyEnv;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LocaleRenderTest extends WebTestCase
{
    use EnsuresSymfonyEnv;

    public static function setUpBeforeClass(): void
    {
        self::ensureSymfonyEnv();
    }

    public function testGuestWithDanishAcceptLanguageGetsDanishLangAttribute(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login', server: ['HTTP_ACCEPT_LANGUAGE' => 'da-DK,da;q=0.9']);

        self::assertResponseIsSuccessful();
        self::assertSame('da', $crawler->filter('html')->attr('lang'));
    }

    public function testGuestWithEnglishAcceptLanguageGetsEnglishLangAttribute(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login', server: ['HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9']);

        self::assertResponseIsSuccessful();
        self::assertSame('en', $crawler->filter('html')->attr('lang'));
    }

    public function testTranslationKeysResolveAndDoNotLeak(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login', server: ['HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9']);

        self::assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();

        // Seeded English copy renders (proves keys resolve through the catalog).
        self::assertStringContainsString('Welcome to Spoked', $html);
        self::assertStringContainsString('Continue', $html);

        // No raw translation keys leaked into the markup.
        self::assertStringNotContainsString('auth.login', $html);
        self::assertStringNotContainsString('common.here', $html);

        // The JS i18n catalog is published with resolved values, not raw keys.
        self::assertStringContainsString('window.appI18n', $html);
        self::assertStringContainsString('"heatmap.drop_pin":"Drop a pin"', $html);
    }
}
