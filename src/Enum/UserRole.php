<?php

namespace App\Enum;

enum UserRole: string
{
    case Photographer = 'ROLE_PHOTOGRAPHER';
    case Influencer = 'ROLE_INFLUENCER';

    public function label(): string
    {
        return match($this) {
            self::Photographer => 'Photographer',
            self::Influencer => 'Influencer',
        };
    }
}
