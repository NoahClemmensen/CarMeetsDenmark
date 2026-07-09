<?php

namespace App\Enum;

enum EventRepeatFrequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    /** Returns a translation key (messages domain); translate before display. */
    public function label(): string
    {
        return match ($this) {
            self::Daily => 'form.frequency.daily',
            self::Weekly => 'form.frequency.weekly',
            self::Monthly => 'form.frequency.monthly',
            self::Yearly => 'form.frequency.yearly',
        };
    }
}
