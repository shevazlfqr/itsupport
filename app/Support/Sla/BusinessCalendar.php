<?php

namespace App\Support\Sla;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;

final readonly class BusinessCalendar
{
    public function __construct(
        public string $timezone,
        public array $workingDays,
        public array $workWindows,
        public array $holidayDates,
        public bool $verified,
        public ?string $verifiedFrom,
        public ?string $verifiedThrough,
        public ?string $scheduleVersion,
        public ?string $holidaySource,
        public ?string $holidayVersion,
    ) {}

    public static function fromConfig(array $config): self
    {
        $timezone = $config['timezone'] ?? 'Asia/Jakarta';
        $timezoneIsValid = is_string($timezone);
        try {
            if ($timezoneIsValid) {
                new DateTimeZone($timezone);
            }
        } catch (\Throwable) {
            $timezoneIsValid = false;
        }

        $timezoneForValidation = $timezoneIsValid ? $timezone : 'UTC';
        $verifiedFrom = self::localDate($config['verified_from'] ?? null, $timezoneForValidation);
        $verifiedThrough = self::localDate($config['verified_through'] ?? null, $timezoneForValidation);
        $scheduleVersion = self::nonEmptyString($config['schedule_version'] ?? null);
        $holidayConfig = is_array($config['holidays'] ?? null) ? $config['holidays'] : [];
        $holidaySource = self::nonEmptyString($holidayConfig['source'] ?? null);
        $holidayVersion = self::nonEmptyString($holidayConfig['version'] ?? null);
        $holidayFrom = self::localDate($holidayConfig['verified_from'] ?? null, $timezoneForValidation);
        $holidayThrough = self::localDate($holidayConfig['verified_through'] ?? null, $timezoneForValidation);

        $holidayDates = [];
        $holidayEntriesValid = true;
        $rawHolidayEntries = $holidayConfig['dates'] ?? [];
        if (! is_array($rawHolidayEntries)) {
            $rawHolidayEntries = [];
            $holidayEntriesValid = false;
        }
        foreach ($rawHolidayEntries as $entry) {
            if (! is_array($entry)) {
                $holidayEntriesValid = false;

                continue;
            }

            $date = self::localDate($entry['date'] ?? null, $timezoneForValidation);
            $type = $entry['type'] ?? null;
            if ($date === null || ! in_array($type, ['national_holiday', 'collective_leave'], true)) {
                $holidayEntriesValid = false;

                continue;
            }

            $holidayDates[] = $date;
        }

        $holidayCoverageComplete = $verifiedFrom !== null
            && $verifiedThrough !== null
            && $holidayFrom !== null
            && $holidayThrough !== null
            && $holidayFrom <= $verifiedFrom
            && $holidayThrough >= $verifiedThrough;
        $verified = $timezoneIsValid
            && ($config['verified'] ?? false) === true
            && $scheduleVersion !== null
            && $verifiedFrom !== null
            && $verifiedThrough !== null
            && $verifiedThrough >= $verifiedFrom
            && ($holidayConfig['verified'] ?? false) === true
            && $holidaySource !== null
            && $holidayVersion !== null
            && $holidayCoverageComplete
            && $holidayEntriesValid;

        return new self(
            timezone: is_string($timezone) ? $timezone : '',
            workingDays: is_array($config['working_days'] ?? null) ? $config['working_days'] : [],
            workWindows: is_array($config['work_windows'] ?? null) ? $config['work_windows'] : [],
            holidayDates: array_values(array_unique($holidayDates)),
            verified: $verified,
            verifiedFrom: $verifiedFrom,
            verifiedThrough: $verifiedThrough,
            scheduleVersion: $scheduleVersion,
            holidaySource: $holidaySource,
            holidayVersion: $holidayVersion,
        );
    }

    public function covers(CarbonImmutable $localDate): bool
    {
        return $this->coversRange($localDate, $localDate);
    }

    public function coversRange(CarbonImmutable $startLocalDate, CarbonImmutable $endLocalDate): bool
    {
        if (! $this->verified
            || $this->verifiedFrom === null
            || $this->verifiedThrough === null
            || $this->scheduleVersion === null
            || $this->holidaySource === null
            || $this->holidayVersion === null) {
            return false;
        }

        try {
            $timezone = new DateTimeZone($this->timezone);
            $start = $startLocalDate->setTimezone($timezone)->toDateString();
            $end = $endLocalDate->setTimezone($timezone)->toDateString();
        } catch (\Throwable) {
            return false;
        }

        return $start >= $this->verifiedFrom
            && $end <= $this->verifiedThrough
            && $end >= $start;
    }

    private static function localDate(mixed $value, string $timezone): ?string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone($timezone));
        $errors = DateTimeImmutable::getLastErrors();

        if ($date === false
            || $date->format('Y-m-d') !== $value
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }

        return $value;
    }

    private static function nonEmptyString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
