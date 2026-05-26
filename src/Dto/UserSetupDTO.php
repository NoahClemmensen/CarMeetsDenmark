<?php

namespace App\Dto;

use App\Enum\UserRole;
use DateTimeZone;
use Symfony\Component\Validator\Constraints as Assert;

class UserSetupDTO
{
    #[Assert\NotBlank(message: 'Name is required.')]
    public string $name = '';

    #[Assert\Length(max: 255, maxMessage: 'Description is too long. Max character limit is {{ limit }}.')]
    public ?string $description = '';

    public ?UserRole $role = null;

    public ?string $instagramUrl = '';

    public ?string $youtubeUrl = '';

    public ?string $facebookUrl = '';

    public ?string $websiteUrl = '';

    #[Assert\Timezone(zone: DateTimeZone::ALL_WITH_BC, message: 'Invalid timezone.')]
    public ?string $timezone = null;

    #[Assert\Length(max: 10)]
    #[Assert\Regex(pattern: '/^[a-zA-Z]{2}(-[a-zA-Z]{2,4})?$/', message: 'Invalid language format.')]
    public ?string $language = null;
}
