<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Converts between the local wall-clock time a host enters (interpreted in the
 * event's IANA timezone) and the UTC instant we store and compare against.
 *
 * Events are stored in UTC so that "now" comparisons (upcoming/past, archiving,
 * start notifications) and recurrence math are timezone- and DST-safe. Display
 * templates convert back to the event's timezone.
 */
final class EventTimeConverter
{
    private const string FORMAT = 'Y-m-d H:i:s';

    /**
     * Reinterpret the wall-clock digits of $wallClock as a time in $timezone,
     * then return the equivalent UTC instant.
     */
    public static function wallClockToUtc(\DateTimeInterface $wallClock, string $timezone): \DateTime
    {
        // Return a mutable \DateTime: the entity columns are DATETIME_MUTABLE,
        // which Doctrine's `datetime` type refuses to persist as immutable.
        $local = \DateTime::createFromFormat(
            self::FORMAT,
            $wallClock->format(self::FORMAT),
            new \DateTimeZone($timezone),
        );
        $local->setTimezone(new \DateTimeZone('UTC'));

        return $local;
    }

    /**
     * Given a UTC instant, return a datetime whose wall-clock digits are the
     * local time in $timezone (tagged UTC so the UTC-based form widget renders
     * those digits unchanged).
     */
    public static function utcToWallClock(\DateTimeInterface $utc, string $timezone): \DateTime
    {
        $local = \DateTimeImmutable::createFromInterface($utc)
            ->setTimezone(new \DateTimeZone($timezone));

        return \DateTime::createFromFormat(
            self::FORMAT,
            $local->format(self::FORMAT),
            new \DateTimeZone('UTC'),
        );
    }
}
