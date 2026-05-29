<?php

declare(strict_types=1);

namespace App\Enum;

enum FollowTargetType: string
{
    case User = 'user';
    case Team = 'team';
}
