<?php

declare(strict_types=1);

namespace App\Tests\Dto;

use App\Dto\SaveEventDTO;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class SaveEventDTOValidationTest extends TestCase
{
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    public function testStartInThePastIsRejectedOnCreate(): void
    {
        $dto = $this->makeDto(new \DateTime('-1 day'), null);

        $violations = $this->validator->validate($dto, null, ['Default', 'create']);

        self::assertGreaterThan(0, $violations->count());
        self::assertSame('Start date cannot be in the past.', $violations->get(0)->getMessage());
    }

    public function testStartInThePastIsAllowedWhenEditing(): void
    {
        $dto = $this->makeDto(new \DateTime('-1 day'), null);

        $violations = $this->validator->validate($dto, null, ['Default']);

        self::assertSame(0, $violations->count());
    }

    public function testEndBeforeStartIsRejected(): void
    {
        $dto = $this->makeDto(new \DateTime('+2 days'), new \DateTime('+1 day'));

        $violations = $this->validator->validate($dto, null, ['Default']);

        self::assertGreaterThan(0, $violations->count());
        self::assertSame('End date must be after the start date.', $violations->get(0)->getMessage());
    }

    public function testNullEndIsAllowed(): void
    {
        $dto = $this->makeDto(new \DateTime('+2 days'), null);

        $violations = $this->validator->validate($dto, null, ['Default', 'create']);

        self::assertSame(0, $violations->count());
    }

    public function testStartIsRejectedWhenLocalTimeIsPastDespiteFutureUtcWallClock(): void
    {
        // 'now' wall-clock digits interpreted in a zone 14h ahead of UTC is an
        // instant ~14h in the past -> must be rejected (this is the original bug).
        $nowWall = (new \DateTime())->format('Y-m-d H:i:s');
        $dto = $this->makeDto(new \DateTime($nowWall), null);
        $dto->timezone = 'Pacific/Kiritimati'; // UTC+14

        $violations = $this->validator->validate($dto, null, ['Default', 'create']);

        self::assertGreaterThan(0, $violations->count());
        self::assertSame('Start date cannot be in the past.', $violations->get(0)->getMessage());
    }

    public function testStartIsAllowedWhenLocalTimeIsFuture(): void
    {
        // Same wall-clock in a zone 11h behind UTC is an instant ~11h in the future.
        $nowWall = (new \DateTime())->format('Y-m-d H:i:s');
        $dto = $this->makeDto(new \DateTime($nowWall), null);
        $dto->timezone = 'Pacific/Midway'; // UTC-11

        $violations = $this->validator->validate($dto, null, ['Default', 'create']);

        self::assertSame(0, $violations->count());
    }

    private function makeDto(\DateTimeInterface $start, ?\DateTimeInterface $end): SaveEventDTO
    {
        $dto = new SaveEventDTO();
        $dto->name = 'Meet';
        $dto->location = 'Copenhagen';
        $dto->startDate = $start;
        $dto->endDate = $end;

        return $dto;
    }
}
