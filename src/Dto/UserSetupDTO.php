<?php

namespace App\Dto;

use DateTimeZone;
use Symfony\Component\Validator\Constraints as Assert;

class UserSetupDTO
{
    #[Assert\NotBlank(message: 'Name is required.')]
    public string $name = '';

    public string $description = '';

    #[Assert\Timezone(zone: DateTimeZone::ALL_WITH_BC, message: 'Invalid timezone.')]
    public ?string $timezone = null;

    #[Assert\Length(max: 10)]
    #[Assert\Regex(pattern: '/^[a-zA-Z]{2}(-[a-zA-Z]{2,4})?$/', message: 'Invalid language format.')]
    public ?string $language = null;
}
