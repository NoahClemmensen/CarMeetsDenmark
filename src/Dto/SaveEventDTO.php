<?php

namespace App\Dto;

use App\Enum\EventRepeatFrequency;
use DateTimeInterface;
use DateTimeZone;
use Symfony\Component\Validator\Constraints as Assert;

class SaveEventDTO
{
    #[Assert\NotBlank(message: 'Name is required.')]
    public string $name = '';

    public ?string $description = '';

    #[Assert\NotBlank(message: 'Start date is required.')]
    public ?DateTimeInterface $startDate = null;

    public ?DateTimeInterface $endDate = null;

    #[Assert\NotBlank(message: 'Location is required.')]
    public string $location = '';

    public ?EventRepeatFrequency $repeatFrequency = null;

    #[Assert\Positive(message: 'Repeat amount must be a positive number.')]
    public ?int $repeatAmount = null;

    #[Assert\Timezone(zone: DateTimeZone::ALL_WITH_BC, message: 'Invalid timezone.')]
    public ?string $timezone = null;

    public bool $private = false;
}
