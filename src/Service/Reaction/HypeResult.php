<?php

declare(strict_types=1);

namespace App\Service\Reaction;

final readonly class HypeResult
{
    public function __construct(
        public int $newCount,
        public bool $isHyped,
    ) {
    }
}
