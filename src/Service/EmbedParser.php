<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\EmbedProvider;
use App\Service\Embed\ParsedEmbed;

class EmbedParser
{
    private const array YOUTUBE_PATTERNS = [
        '#^https?://(?:www\.)?youtube\.com/watch\?(?:.*&)?v=([A-Za-z0-9_-]{11})(?:[&?].*)?$#',
        '#^https?://youtu\.be/([A-Za-z0-9_-]{11})(?:[?].*)?$#',
        '#^https?://(?:www\.)?youtube\.com/embed/([A-Za-z0-9_-]{11})(?:[?].*)?$#',
        '#^https?://(?:www\.)?youtube\.com/shorts/([A-Za-z0-9_-]{11})(?:[?].*)?$#',
    ];

    private const array INSTAGRAM_PATTERNS = [
        '#^https?://(?:www\.)?instagram\.com/p/([A-Za-z0-9_-]+)/?(?:\?.*)?$#',
        '#^https?://(?:www\.)?instagram\.com/reel/([A-Za-z0-9_-]+)/?(?:\?.*)?$#',
    ];

    public function parse(string $url): ?ParsedEmbed
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        foreach (self::YOUTUBE_PATTERNS as $pattern) {
            if (preg_match($pattern, $url, $matches) === 1) {
                return new ParsedEmbed(EmbedProvider::YouTube, $matches[1]);
            }
        }

        foreach (self::INSTAGRAM_PATTERNS as $pattern) {
            if (preg_match($pattern, $url, $matches) === 1) {
                return new ParsedEmbed(EmbedProvider::Instagram, $matches[1]);
            }
        }

        return null;
    }
}
