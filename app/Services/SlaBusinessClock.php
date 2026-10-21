<?php

namespace App\Services;

use App\Support\Sla\BusinessCalendar;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;

class SlaBusinessClock
{
    public function deadlineAfterWorkingSeconds(
        DateTimeInterface $start,
        int $targetWorkingSeconds,
        BusinessCalendar $calendar,
        array $pausedIntervals = [],
    ): ?CarbonImmutable {
        if ($targetWorkingSeconds < 0) {
            throw new InvalidArgumentException('Target waktu kerja tidak boleh negatif.');
        }

        if (! $this->calendarIsUsable($calendar)) {
            return null;
        }

        $pauses = $this->normalizePauses($pausedIntervals);
        if ($pauses === null) {
            return null;
        }

        $startUtc = CarbonImmutable::instance($start)->utc();
        $timezone = $calendar->timezone;
        $localStart = $startUtc->setTimezone($timezone);
        $date = $localStart->startOfDay();
        if (! $calendar->covers($date)) {
            return null;
        }

        $lastDate = $this->localDate($calendar->verifiedThrough, $timezone);
        if ($lastDate === null) {
            return null;
        }

        $remaining = $targetWorkingSeconds;
        while ($date->lessThanOrEqualTo($lastDate)) {
            if ($this->isWorkingDate($date, $calendar)) {
                foreach ($calendar->workWindows as $window) {
                    $bounds = $this->windowBounds($date, $window, $timezone);
                    if ($bounds === null) {
                        return null;
                    }

                    [$windowStart, $windowEnd] = $bounds;
                    $segmentStart = $startUtc->greaterThan($windowStart) ? $startUtc : $windowStart;
                    if ($segmentStart->greaterThanOrEqualTo($windowEnd)) {
                        continue;
                    }

                    foreach ($this->availableSegments($segmentStart, $windowEnd, $pauses) as [$from, $to]) {
                        $available = $to->getTimestamp() - $from->getTimestamp();
                        if ($available <= 0) {
                            continue;
                        }
                        if ($remaining === 0) {
                            return $from;
                        }
                        if ($remaining <= $available) {
                            return $from->addSeconds($remaining);
                        }

                        $remaining -= $available;
                    }
                }
            }

            $date = $date->addDay();
        }

        return null;
    }

    public function workingSecondsBetween(
        DateTimeInterface $start,
        DateTimeInterface $end,
        BusinessCalendar $calendar,
        array $pausedIntervals = [],
    ): ?int {
        $startUtc = CarbonImmutable::instance($start)->utc();
        $endUtc = CarbonImmutable::instance($end)->utc();
        if ($endUtc->lessThan($startUtc)) {
            throw new InvalidArgumentException('Waktu akhir interval SLA tidak boleh lebih awal dari waktu mulai.');
        }

        if (! $this->calendarIsUsable($calendar)) {
            return null;
        }

        $pauses = $this->normalizePauses($pausedIntervals);
        if ($pauses === null) {
            return null;
        }

        $timezone = $calendar->timezone;
        $startDate = $startUtc->setTimezone($timezone)->startOfDay();
        $endDate = $endUtc->setTimezone($timezone)->startOfDay();
        if (! $calendar->coversRange($startDate, $endDate)) {
            return null;
        }
        if ($endUtc->equalTo($startUtc)) {
            return 0;
        }

        $total = 0;
        $date = $startDate;
        while ($date->lessThanOrEqualTo($endDate)) {
            if ($this->isWorkingDate($date, $calendar)) {
                foreach ($calendar->workWindows as $window) {
                    $bounds = $this->windowBounds($date, $window, $timezone);
                    if ($bounds === null) {
                        return null;
                    }

                    [$windowStart, $windowEnd] = $bounds;
                    $from = $startUtc->greaterThan($windowStart) ? $startUtc : $windowStart;
                    $to = $endUtc->lessThan($windowEnd) ? $endUtc : $windowEnd;
                    if ($to->lessThanOrEqualTo($from)) {
                        continue;
                    }

                    foreach ($this->availableSegments($from, $to, $pauses) as [$segmentStart, $segmentEnd]) {
                        $total += max(0, $segmentEnd->getTimestamp() - $segmentStart->getTimestamp());
                    }
                }
            }

            $date = $date->addDay();
        }

        return $total;
    }

    private function calendarIsUsable(BusinessCalendar $calendar): bool
    {
        if (! $calendar->verified
            || $calendar->verifiedFrom === null
            || $calendar->verifiedThrough === null
            || $calendar->scheduleVersion === null
            || $calendar->holidaySource === null
            || $calendar->holidayVersion === null
            || $calendar->workWindows === []
            || $calendar->workingDays === []) {
            return false;
        }

        try {
            new DateTimeZone($calendar->timezone);
            if ($this->localDate($calendar->verifiedFrom, $calendar->timezone) === null
                || $this->localDate($calendar->verifiedThrough, $calendar->timezone) === null
                || $calendar->verifiedThrough < $calendar->verifiedFrom) {
                return false;
            }
        } catch (\Throwable) {
            return false;
        }

        foreach ($calendar->workingDays as $day) {
            if (! is_int($day) || $day < 1 || $day > 7) {
                return false;
            }
        }
        if ($calendar->workingDays !== [1, 2, 3, 4, 5]
            || $calendar->workWindows !== [['08:30', '12:00'], ['13:00', '17:30']]) {
            return false;
        }

        $previousEnd = null;
        foreach ($calendar->workWindows as $window) {
            if (! is_array($window) || count($window) !== 2
                || ! $this->isValidTime($window[0])
                || ! $this->isValidTime($window[1])
                || $window[0] >= $window[1]
                || ($previousEnd !== null && $window[0] < $previousEnd)) {
                return false;
            }

            $previousEnd = $window[1];
        }

        foreach ($calendar->holidayDates as $holidayDate) {
            if (! is_string($holidayDate)
                || $this->localDate($holidayDate, $calendar->timezone) === null) {
                return false;
            }
        }

        return true;
    }

    private function isWorkingDate(CarbonImmutable $date, BusinessCalendar $calendar): bool
    {
        return in_array($date->dayOfWeekIso, $calendar->workingDays, true)
            && ! in_array($date->toDateString(), $calendar->holidayDates, true);
    }

    private function windowBounds(CarbonImmutable $date, array $window, string $timezone): ?array
    {
        if (count($window) !== 2 || ! $this->isValidTime($window[0]) || ! $this->isValidTime($window[1])) {
            return null;
        }

        $day = $date->toDateString();
        $start = CarbonImmutable::parse($day.' '.$window[0], $timezone)->utc();
        $end = CarbonImmutable::parse($day.' '.$window[1], $timezone)->utc();

        return [$start, $end];
    }

    private function normalizePauses(array $intervals): ?array
    {
        $pauses = [];
        foreach ($intervals as $interval) {
            if (! is_array($interval) || ! isset($interval['start'], $interval['end'])) {
                return null;
            }

            $start = $this->instantUtc($interval['start']);
            $end = $this->instantUtc($interval['end']);
            if ($start === null || $end === null || $end->lessThanOrEqualTo($start)) {
                return null;
            }

            $pauses[] = [$start, $end];
        }

        usort($pauses, fn (array $left, array $right): int => $left[0]->getTimestamp() <=> $right[0]->getTimestamp());
        $merged = [];
        foreach ($pauses as [$start, $end]) {
            $lastIndex = array_key_last($merged);
            if ($lastIndex === null || $start->greaterThan($merged[$lastIndex][1])) {
                $merged[] = [$start, $end];

                continue;
            }

            if ($end->greaterThan($merged[$lastIndex][1])) {
                $merged[$lastIndex][1] = $end;
            }
        }

        return $merged;
    }

    private function availableSegments(CarbonImmutable $from, CarbonImmutable $to, array $pauses): array
    {
        $segments = [];
        $cursor = $from;

        foreach ($pauses as [$pauseStart, $pauseEnd]) {
            if ($pauseEnd->lessThanOrEqualTo($cursor)) {
                continue;
            }
            if ($pauseStart->greaterThanOrEqualTo($to)) {
                break;
            }

            if ($pauseStart->greaterThan($cursor)) {
                $segments[] = [$cursor, $pauseStart->lessThan($to) ? $pauseStart : $to];
            }

            if ($pauseEnd->greaterThan($cursor)) {
                $cursor = $pauseEnd;
            }
            if ($cursor->greaterThanOrEqualTo($to)) {
                return $segments;
            }
        }

        if ($cursor->lessThan($to)) {
            $segments[] = [$cursor, $to];
        }

        return $segments;
    }

    private function instantUtc(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->utc();
        }
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value, 'UTC')->utc();
        } catch (\Throwable) {
            return null;
        }
    }

    private function isValidTime(mixed $time): bool
    {
        return is_string($time)
            && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time) === 1;
    }

    private function localDate(?string $date, string $timezone): ?CarbonImmutable
    {
        if ($date === null || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        try {
            $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date, $timezone);
        } catch (\Throwable) {
            return null;
        }

        return $parsed !== false && $parsed->toDateString() === $date ? $parsed : null;
    }
}
