<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Service\EventTimeConverter;
use PHPUnit\Framework\TestCase;

final class EventTimeConverterTest extends TestCase
{
    public function testWallClockToUtcAppliesSummerOffset(): void
    {
        // Europe/Copenhagen is UTC+2 in June (DST).
        $wall = new \DateTime('2026-06-02 12:52:00');

        $utc = EventTimeConverter::wallClockToUtc($wall, 'Europe/Copenhagen');

        self::assertSame('2026-06-02 10:52:00', $utc->format('Y-m-d H:i:s'));
        self::assertSame('UTC', $utc->getTimezone()->getName());
    }

    public function testWallClockToUtcAppliesWinterOffset(): void
    {
        // Europe/Copenhagen is UTC+1 in January (no DST).
        $wall = new \DateTime('2026-01-15 12:00:00');

        $utc = EventTimeConverter::wallClockToUtc($wall, 'Europe/Copenhagen');

        self::assertSame('2026-01-15 11:00:00', $utc->format('Y-m-d H:i:s'));
    }

    public function testWallClockToUtcIsNoOpForUtc(): void
    {
        $wall = new \DateTime('2026-06-02 12:52:00');

        $utc = EventTimeConverter::wallClockToUtc($wall, 'UTC');

        self::assertSame('2026-06-02 12:52:00', $utc->format('Y-m-d H:i:s'));
    }

    public function testUtcToWallClockGivesLocalDigits(): void
    {
        // A stored UTC instant, hydrated by Doctrine as UTC.
        $utc = new \DateTime('2026-06-02 10:52:00', new \DateTimeZone('UTC'));

        $wall = EventTimeConverter::utcToWallClock($utc, 'Europe/Copenhagen');

        // The form widget reads wall-clock digits, so we expect 12:52 here.
        self::assertSame('2026-06-02 12:52:00', $wall->format('Y-m-d H:i:s'));
    }

    public function testRoundTrip(): void
    {
        $wall = new \DateTime('2026-06-02 12:52:00');

        $utc = EventTimeConverter::wallClockToUtc($wall, 'Europe/Copenhagen');
        $back = EventTimeConverter::utcToWallClock($utc, 'Europe/Copenhagen');

        self::assertSame($wall->format('Y-m-d H:i:s'), $back->format('Y-m-d H:i:s'));
    }
}
