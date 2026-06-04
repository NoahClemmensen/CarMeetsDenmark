<?php

namespace App\Dto;

use App\Enum\EventRepeatFrequency;
use App\Service\EventTimeConverter;
use DateTimeInterface;
use DateTimeZone;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class SaveEventDTO
{
    #[Assert\NotBlank(message: 'Name is required.')]
    public string $name = '';

    public ?string $description = '';

    #[Assert\NotBlank(message: 'Start date is required.')]
    public ?DateTimeInterface $startDate = null;

    #[Assert\GreaterThan(
        propertyPath: 'startDate',
        message: 'End date must be after the start date.',
    )]
    public ?DateTimeInterface $endDate = null;

    #[Assert\NotBlank(message: 'Location is required.')]
    public string $location = '';

    public ?EventRepeatFrequency $repeatFrequency = null;

    #[Assert\Positive(message: 'Repeat amount must be a positive number.')]
    public ?int $repeatAmount = null;

    #[Assert\Timezone(zone: DateTimeZone::ALL_WITH_BC, message: 'Invalid timezone.')]
    public ?string $timezone = null;

    public bool $private = false;

    /**
     * Reject a start time that is in the past, comparing in the event's own
     * timezone (the entered time is local wall-clock, not UTC). Only enforced
     * when creating, so editing a past event stays possible.
     */
    #[Assert\Callback(groups: ['create'])]
    public function validateStartNotInPast(ExecutionContextInterface $context): void
    {
        if ($this->startDate === null) {
            return;
        }

        $timezone = ($this->timezone ?? '') !== '' ? $this->timezone : 'UTC';

        try {
            $startUtc = EventTimeConverter::wallClockToUtc($this->startDate, $timezone);
        } catch (\Exception) {
            return; // an invalid timezone is reported separately by the Timezone constraint
        }

        if ($startUtc < new \DateTimeImmutable()) {
            $context->buildViolation('Start date cannot be in the past.')
                ->atPath('startDate')
                ->addViolation();
        }
    }
}
