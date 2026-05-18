<?php

namespace App\Enum;

enum EmbedProvider: string
{
    case YouTube = 'youtube';
    case Instagram = 'instagram';

    public function label(): string
    {
        return match ($this) {
            self::YouTube => 'YouTube',
            self::Instagram => 'Instagram',
        };
    }
}
