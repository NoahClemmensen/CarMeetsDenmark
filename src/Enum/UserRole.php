<?php

namespace App\Enum;

enum UserRole: string
{
    case Photographer = 'ROLE_PHOTOGRAPHER';
    case Influencer = 'ROLE_INFLUENCER';

    /** Returns a translation key (messages domain); translate before display. */
    public function label(): string
    {
        return match($this) {
            self::Photographer => 'form.role.photographer',
            self::Influencer => 'form.role.influencer',
        };
    }
}
