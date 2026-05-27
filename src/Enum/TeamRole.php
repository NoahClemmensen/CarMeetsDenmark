<?php

declare(strict_types=1);

namespace App\Enum;

enum TeamRole: string
{
    case Owner = 'owner';
    case Member = 'member';
}
