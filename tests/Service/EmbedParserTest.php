<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Enum\EmbedProvider;
use App\Service\Embed\ParsedEmbed;
use App\Service\EmbedParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EmbedParserTest extends TestCase
{
    private EmbedParser $parser;

    protected function setUp(): void
    {
        $this->parser = new EmbedParser();
    }

    public static function youtubeUrls(): array
    {
        return [
            ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
            ['https://youtube.com/watch?v=dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
            ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=42s', 'dQw4w9WgXcQ'],
            ['https://youtu.be/dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
            ['https://youtu.be/dQw4w9WgXcQ?si=abc', 'dQw4w9WgXcQ'],
            ['https://www.youtube.com/embed/dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
            ['https://www.youtube.com/shorts/dQw4w9WgXcQ', 'dQw4w9WgXcQ'],
        ];
    }

    #[DataProvider('youtubeUrls')]
    public function testParsesYouTubeUrls(string $url, string $expectedId): void
    {
        $result = $this->parser->parse($url);

        self::assertInstanceOf(ParsedEmbed::class, $result);
        self::assertSame(EmbedProvider::YouTube, $result->provider);
        self::assertSame($expectedId, $result->externalId);
    }

    public static function instagramUrls(): array
    {
        return [
            ['https://www.instagram.com/p/CabcDEF123/', 'CabcDEF123'],
            ['https://instagram.com/p/CabcDEF123', 'CabcDEF123'],
            ['https://www.instagram.com/reel/CabcDEF123/', 'CabcDEF123'],
            ['https://instagram.com/reel/CabcDEF123', 'CabcDEF123'],
        ];
    }

    #[DataProvider('instagramUrls')]
    public function testParsesInstagramUrls(string $url, string $expectedId): void
    {
        $result = $this->parser->parse($url);

        self::assertInstanceOf(ParsedEmbed::class, $result);
        self::assertSame(EmbedProvider::Instagram, $result->provider);
        self::assertSame($expectedId, $result->externalId);
    }

    public static function unsupportedUrls(): array
    {
        return [
            ['https://vimeo.com/12345'],
            ['https://tiktok.com/@user/video/123'],
            ['not a url at all'],
            [''],
            ['https://example.com/'],
            ['https://www.youtube.com/'],
            ['https://www.youtube.com/watch'],
            ['https://www.youtube.com/watch?foo=bar'],
            ['https://instagram.com/someuser/'],
        ];
    }

    #[DataProvider('unsupportedUrls')]
    public function testReturnsNullForUnsupportedUrls(string $url): void
    {
        self::assertNull($this->parser->parse($url));
    }
}
