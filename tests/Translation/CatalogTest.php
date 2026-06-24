<?php

declare(strict_types=1);

namespace App\Tests\Translation;

use App\Tests\EnsuresSymfonyEnv;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class CatalogTest extends KernelTestCase
{
    use EnsuresSymfonyEnv;

    public static function setUpBeforeClass(): void
    {
        self::ensureSymfonyEnv();
    }

    protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }

    public function testEnglishCatalogTranslatesSeededKeys(): void
    {
        self::bootKernel();
        $translator = static::getContainer()->get(TranslatorInterface::class);

        self::assertSame('Language', $translator->trans('settings.language.label', [], 'messages', 'en'));
        self::assertSame('Discover', $translator->trans('nav.discover', [], 'messages', 'en'));
    }

    public function testDanishCatalogTranslatesSeededKeys(): void
    {
        self::bootKernel();
        $translator = static::getContainer()->get(TranslatorInterface::class);

        self::assertSame('Sprog', $translator->trans('settings.language.label', [], 'messages', 'da'));
        self::assertSame('Opdag', $translator->trans('nav.discover', [], 'messages', 'da'));
    }
}
