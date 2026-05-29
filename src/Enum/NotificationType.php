<?php

declare(strict_types=1);

namespace App\Enum;

enum NotificationType: string
{
    case TeamNewEvent = 'team_new_event';
    case UserRsvpGoing = 'user_rsvp_going';
    case UserNewPost = 'user_new_post';
}
