<?php

declare(strict_types=1);

namespace App\Service\Embed;

use App\Enum\EmbedProvider;

final readonly class ParsedEmbed
{
    public function __construct(
        public EmbedProvider $provider,
        public string $externalId,
    ) {
    }
}
