<?php

namespace App\Enum;

enum ParticipationStatus: string
{
    case Going = 'going';
    case Maybe = 'maybe';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::Going => 'Going',
            self::Maybe => 'Maybe',
            self::Declined => "Can't go",
        };
    }
}
