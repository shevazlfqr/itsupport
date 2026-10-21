<?php

namespace Tests\Unit;

use App\Services\SlaBusinessClock;
use App\Support\Sla\BusinessCalendar;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SlaBusinessClockTest extends TestCase
{
    public function test_monday_opening_starts_the_business_clock(): void
    {
        $deadline = $this->clock()->deadlineAfterWorkingSeconds(
            $this->local('2026-10-19 08:30:00'),
            3600,
            $this->calendar(),
        );

        $this->assertLocal('2026-10-19 09:30:00', $deadline);
    }

    public function test_friday_sixteen_plus_one_hour_is_friday_seventeen(): void
    {
        $deadline = $this->clock()->deadlineAfterWorkingSeconds(
            $this->local('2026-10-16 16:00:00'),
            3600,
            $this->calendar(),
        );

        $this->assertLocal('2026-10-16 17:00:00', $deadline);
    }

    public function test_deadline_exactly_at_close_is_valid_and_extra_time_continues_next_open_interval(): void
    {
        $clock = $this->clock();
        $calendar = $this->calendar();

        $atClose = $clock->deadlineAfterWorkingSeconds($this->local('2026-10-16 16:00:00'), 5400, $calendar);
        $afterClose = $clock->deadlineAfterWorkingSeconds($this->local('2026-10-16 16:00:00'), 7200, $calendar);
        $fullWorkday = $clock->deadlineAfterWorkingSeconds($this->local('2026-10-19 08:30:00'), 28800, $calendar);

        $this->assertLocal('2026-10-16 17:30:00', $atClose);
        $this->assertLocal('2026-10-19 09:00:00', $afterClose);
        $this->assertLocal('2026-10-19 17:30:00', $fullWorkday);
    }

    public function test_lunch_break_is_excluded_and_lunch_start_normalizes_to_one_pm(): void
    {
        $clock = $this->clock();
        $calendar = $this->calendar();

        $crossesLunch = $clock->deadlineAfterWorkingSeconds($this->local('2026-10-19 11:30:00'), 3600, $calendar);
        $startsDuringLunch = $clock->deadlineAfterWorkingSeconds($this->local('2026-10-19 12:30:00'), 1, $calendar);
        $atLunchBoundary = $clock->deadlineAfterWorkingSeconds($this->local('2026-10-19 12:00:00'), 0, $calendar);
        $fullTarget = $clock->deadlineAfterWorkingSeconds($this->local('2026-10-19 11:30:00'), 28800, $calendar);

        $this->assertLocal('2026-10-19 13:30:00', $crossesLunch);
        $this->assertLocal('2026-10-19 13:00:01', $startsDuringLunch);
        $this->assertLocal('2026-10-19 13:00:00', $atLunchBoundary);
        $this->assertLocal('2026-10-20 11:30:00', $fullTarget);
    }

    public function test_start_after_close_normalizes_to_next_weekday_opening(): void
    {
        $clock = $this->clock();
        $calendar = $this->calendar();

        $oneHour = $clock->deadlineAfterWorkingSeconds($this->local('2026-10-19 17:31:00'), 3600, $calendar);
        $oneHourFromExactClose = $clock->deadlineAfterWorkingSeconds($this->local('2026-10-19 17:30:00'), 3600, $calendar);
        $zeroAtClose = $clock->deadlineAfterWorkingSeconds($this->local('2026-10-19 17:30:00'), 0, $calendar);

        $this->assertLocal('2026-10-20 09:30:00', $oneHour);
        $this->assertLocal('2026-10-20 09:30:00', $oneHourFromExactClose);
        $this->assertLocal('2026-10-20 08:30:00', $zeroAtClose);
    }

    public function test_saturday_and_sunday_start_at_next_monday_opening(): void
    {
        $clock = $this->clock();
        $calendar = $this->calendar();

        $saturday = $clock->deadlineAfterWorkingSeconds($this->local('2026-10-17 10:00:00'), 3600, $calendar);
        $sunday = $clock->deadlineAfterWorkingSeconds($this->local('2026-10-18 10:00:00'), 3600, $calendar);
        $zeroOnWeekend = $clock->deadlineAfterWorkingSeconds($this->local('2026-10-17 10:00:00'), 0, $calendar);

        $this->assertLocal('2026-10-19 09:30:00', $saturday);
        $this->assertLocal('2026-10-19 09:30:00', $sunday);
        $this->assertLocal('2026-10-19 08:30:00', $zeroOnWeekend);
    }

    public function test_sunday_noon_ticket_receives_deadlines_from_monday_opening(): void
    {
        $clock = $this->clock();
        $start = $this->local('2026-10-18 12:00:00');

        $response = $clock->deadlineAfterWorkingSeconds($start, 3600, $this->calendar());
        $resolution = $clock->deadlineAfterWorkingSeconds($start, 28800, $this->calendar());

        $this->assertLocal('2026-10-19 09:30:00', $response);
        $this->assertLocal('2026-10-19 17:30:00', $resolution);
    }

    public function test_elapsed_time_excludes_evenings_weekend_and_break(): void
    {
        $elapsed = $this->clock()->workingSecondsBetween(
            $this->local('2026-10-16 16:00:00'),
            $this->local('2026-10-19 13:30:00'),
            $this->calendar(),
        );

        $this->assertSame(19800, $elapsed);
    }

    public function test_reversed_elapsed_interval_is_invalid_instead_of_zero(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->clock()->workingSecondsBetween(
            $this->local('2026-10-19 10:00:00'),
            $this->local('2026-10-19 09:00:00'),
            $this->calendar(),
        );
    }

    public function test_reversed_interval_remains_invalid_when_calendar_is_unavailable(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->clock()->workingSecondsBetween(
            $this->local('2026-10-19 10:00:00'),
            $this->local('2026-10-19 09:00:00'),
            $this->calendar(verifiedThrough: null),
        );
    }

    public function test_equal_elapsed_interval_is_a_valid_zero_duration(): void
    {
        $elapsed = $this->clock()->workingSecondsBetween(
            $this->local('2026-10-19 10:00:00'),
            $this->local('2026-10-19 10:00:00'),
            $this->calendar(),
        );

        $this->assertSame(0, $elapsed);
    }

    public function test_positive_elapsed_interval_returns_working_seconds(): void
    {
        $elapsed = $this->clock()->workingSecondsBetween(
            $this->local('2026-10-19 08:30:00'),
            $this->local('2026-10-19 10:00:00'),
            $this->calendar(),
        );

        $this->assertSame(5400, $elapsed);
    }

    public function test_national_holiday_and_collective_leave_are_both_excluded(): void
    {
        $clock = $this->clock();
        $nationalHoliday = $this->calendar([
            ['date' => '2026-10-19', 'type' => 'national_holiday'],
        ]);
        $collectiveLeave = $this->calendar([
            ['date' => '2026-10-20', 'type' => 'collective_leave'],
        ]);

        $response = $clock->deadlineAfterWorkingSeconds($this->local('2026-10-16 17:00:00'), 3600, $nationalHoliday);
        $resolution = $clock->deadlineAfterWorkingSeconds($this->local('2026-10-16 17:00:00'), 28800, $nationalHoliday);
        $collectiveLeaveDeadline = $clock->deadlineAfterWorkingSeconds($this->local('2026-10-19 08:30:00'), 32400, $collectiveLeave);

        $this->assertLocal('2026-10-20 09:00:00', $response);
        $this->assertLocal('2026-10-20 17:00:00', $resolution);
        $this->assertLocal('2026-10-21 09:30:00', $collectiveLeaveDeadline);
    }

    public function test_deadlines_cross_month_and_year_boundaries(): void
    {
        $clock = $this->clock();
        $calendar = $this->calendar([
            ['date' => '2027-01-01', 'type' => 'national_holiday'],
        ]);

        $monthEnd = $clock->deadlineAfterWorkingSeconds($this->local('2026-10-30 16:00:00'), 7200, $calendar);
        $yearEnd = $clock->deadlineAfterWorkingSeconds($this->local('2026-12-31 16:00:00'), 7200, $calendar);

        $this->assertLocal('2026-11-02 09:00:00', $monthEnd);
        $this->assertLocal('2027-01-04 09:00:00', $yearEnd);
    }

    public function test_calendar_dates_are_local_even_when_machine_timezone_differs(): void
    {
        $originalTimezone = date_default_timezone_get();

        try {
            date_default_timezone_set('Pacific/Honolulu');
            $deadline = $this->clock()->deadlineAfterWorkingSeconds(
                $this->local('2026-10-16 16:00:00'),
                3600,
                $this->calendar(),
            );
        } finally {
            date_default_timezone_set($originalTimezone);
        }

        $this->assertLocal('2026-10-16 17:00:00', $deadline);
    }

    public function test_opening_closing_and_zero_duration_boundaries_are_consistent(): void
    {
        $clock = $this->clock();
        $calendar = $this->calendar();

        $atOpen = $clock->deadlineAfterWorkingSeconds($this->local('2026-10-19 08:30:00'), 0, $calendar);
        $toLunch = $clock->deadlineAfterWorkingSeconds($this->local('2026-10-19 08:30:00'), 12600, $calendar);
        $toAfternoonOpen = $clock->deadlineAfterWorkingSeconds($this->local('2026-10-19 08:30:00'), 12601, $calendar);
        $nextOpening = $clock->deadlineAfterWorkingSeconds($this->local('2026-10-16 17:30:00'), 0, $calendar);

        $this->assertLocal('2026-10-19 08:30:00', $atOpen);
        $this->assertLocal('2026-10-19 12:00:00', $toLunch);
        $this->assertLocal('2026-10-19 13:00:01', $toAfternoonOpen);
        $this->assertLocal('2026-10-19 08:30:00', $nextOpening);
    }

    public function test_business_clock_refuses_unverified_or_out_of_coverage_holiday_calendars(): void
    {
        $unverified = new BusinessCalendar(
            timezone: 'Asia/Jakarta',
            workingDays: [1, 2, 3, 4, 5],
            workWindows: [['08:30', '12:00'], ['13:00', '17:30']],
            holidayDates: [],
            verified: false,
            verifiedFrom: null,
            verifiedThrough: null,
            scheduleVersion: null,
            holidaySource: null,
            holidayVersion: null,
        );
        $limitedCoverage = $this->calendar(verifiedThrough: '2026-10-18');

        $this->assertNull($this->clock()->deadlineAfterWorkingSeconds($this->local('2026-10-19 08:30:00'), 3600, $unverified));
        $this->assertNull($this->clock()->deadlineAfterWorkingSeconds($this->local('2026-10-19 08:30:00'), 3600, $limitedCoverage));
    }

    public function test_elapsed_time_outside_verified_calendar_coverage_is_unavailable_not_zero(): void
    {
        $elapsed = $this->clock()->workingSecondsBetween(
            $this->local('2026-10-16 16:00:00'),
            $this->local('2026-10-19 09:00:00'),
            $this->calendar(verifiedThrough: '2026-10-18'),
        );

        $this->assertNull($elapsed);
    }

    public function test_configured_holiday_calendar_requires_source_version_and_complete_coverage(): void
    {
        $calendar = BusinessCalendar::fromConfig([
            'timezone' => 'Asia/Jakarta',
            'working_days' => [1, 2, 3, 4, 5],
            'work_windows' => [['08:30', '12:00'], ['13:00', '17:30']],
            'verified' => true,
            'schedule_version' => 'schedule-v1',
            'verified_from' => '2026-01-01',
            'verified_through' => '2027-12-31',
            'holidays' => [
                'source' => null,
                'version' => null,
                'verified' => true,
                'verified_from' => '2026-01-01',
                'verified_through' => '2026-12-31',
                'dates' => [],
            ],
        ]);

        $this->assertFalse($calendar->verified);
        $this->assertFalse($calendar->covers($this->local('2026-10-19 00:00:00')));
        $this->assertNull($this->clock()->deadlineAfterWorkingSeconds(
            $this->local('2026-10-19 08:30:00'),
            3600,
            $calendar,
        ));
    }

    public function test_malformed_and_open_pause_intervals_do_not_produce_sla_results(): void
    {
        $clock = $this->clock();
        $start = $this->local('2026-10-19 08:30:00');

        $this->assertNull($clock->deadlineAfterWorkingSeconds($start, 3600, $this->calendar(), [[
            'start' => 'not a timestamp',
            'end' => '2026-10-19 11:00:00 Asia/Jakarta',
        ]]));
        $this->assertNull($clock->deadlineAfterWorkingSeconds($start, 3600, $this->calendar(), [[
            'start' => $this->local('2026-10-19 10:00:00'),
        ]]));
    }

    public function test_requester_pause_is_subtracted_once_and_no_pause_means_waiting_on_it_keeps_running(): void
    {
        $clock = $this->clock();
        $start = $this->local('2026-10-19 08:30:00');
        $requesterWait = [[
            'start' => $this->local('2026-10-19 10:00:00'),
            'end' => $this->local('2026-10-20 08:30:00'),
        ]];

        $pausedDeadline = $clock->deadlineAfterWorkingSeconds($start, 28800, $this->calendar(), $requesterWait);
        $unpausedDeadline = $clock->deadlineAfterWorkingSeconds($start, 28800, $this->calendar());
        $elapsed = $clock->workingSecondsBetween($start, $this->local('2026-10-20 08:30:00'), $this->calendar(), $requesterWait);

        $this->assertLocal('2026-10-20 16:00:00', $pausedDeadline);
        $this->assertLocal('2026-10-19 17:30:00', $unpausedDeadline);
        $this->assertSame(5400, $elapsed);
    }

    public function test_overlapping_pause_intervals_are_not_subtracted_twice(): void
    {
        $elapsed = $this->clock()->workingSecondsBetween(
            $this->local('2026-10-19 08:30:00'),
            $this->local('2026-10-19 17:30:00'),
            $this->calendar(),
            [
                ['start' => $this->local('2026-10-19 09:00:00'), 'end' => $this->local('2026-10-19 11:00:00')],
                ['start' => $this->local('2026-10-19 10:00:00'), 'end' => $this->local('2026-10-19 14:00:00')],
            ],
        );

        $this->assertSame(14400, $elapsed);
    }

    private function calendar(array $holidays = [], ?string $verifiedThrough = '2027-12-31'): BusinessCalendar
    {
        return new BusinessCalendar(
            timezone: 'Asia/Jakarta',
            workingDays: [1, 2, 3, 4, 5],
            workWindows: [['08:30', '12:00'], ['13:00', '17:30']],
            holidayDates: array_column($holidays, 'date'),
            verified: $verifiedThrough !== null,
            verifiedFrom: '2026-01-01',
            verifiedThrough: $verifiedThrough,
            scheduleVersion: 'synthetic-weekdays-v1',
            holidaySource: 'synthetic test fixture; not official holiday data',
            holidayVersion: 'synthetic-holidays-v1',
        );
    }

    private function clock(): SlaBusinessClock
    {
        return new SlaBusinessClock;
    }

    private function local(string $value): CarbonImmutable
    {
        return CarbonImmutable::parse($value, 'Asia/Jakarta');
    }

    private function assertLocal(string $expected, ?CarbonImmutable $actual): void
    {
        $this->assertNotNull($actual);
        $this->assertSame($expected, $actual->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s'));
    }
}
