<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SlaDeadlineCalculator;
use App\Services\TicketService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class SlaCalendarIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_new_calculation_is_unavailable_when_kpi_gate_is_off_or_calendar_is_unverified(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-16 16:00:00', 'Asia/Jakarta'));
        $this->configureApprovedTargets();
        config(['sla.enabled' => false]);

        $this->assertNull(app(SlaDeadlineCalculator::class)->calculateFromTicketCreation(
            'urgent',
            CarbonImmutable::parse('2026-10-16 16:00:00', 'Asia/Jakarta'),
        ));

        config(['sla.enabled' => true, 'sla.calendar.holidays.verified' => false]);
        $this->assertNull(app(SlaDeadlineCalculator::class)->calculateFromTicketCreation(
            'urgent',
            CarbonImmutable::parse('2026-10-16 16:00:00', 'Asia/Jakarta'),
        ));
    }

    public function test_gated_ticket_deadlines_use_creation_event_and_the_shared_business_clock(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-16 16:00:00', 'Asia/Jakarta'));
        $this->configureApprovedTargets();

        $requester = User::query()->create([
            'name' => 'SLA test requester',
            'email' => 'sla-'.Str::uuid().'@example.test',
            'password' => Hash::make('test-password'),
            'role' => 'employee',
            'department' => 'IT',
            'job_title' => 'Staff',
            'active' => true,
        ]);
        $ticket = app(TicketService::class)->create($requester, [
            'subject' => 'SLA calendar fixture',
            'description' => 'Test-only ticket.',
            'category' => 'Software',
            'priority' => 'urgent',
        ]);
        $creationEvent = $ticket->events()->where('event_type', 'ticket.created')->firstOrFail();
        $firstCalculation = app(SlaDeadlineCalculator::class)->calculateFromTicketCreation(
            'urgent',
            $creationEvent->occurred_at_utc,
        );

        $ticket->forceFill(['updated_at' => CarbonImmutable::parse('2026-10-20 16:00:00', 'Asia/Jakarta')])->saveQuietly();
        $secondCalculation = app(SlaDeadlineCalculator::class)->calculateFromTicketCreation(
            'urgent',
            $creationEvent->occurred_at_utc,
        );

        $this->assertNotNull($firstCalculation);
        $this->assertSame('P1', $firstCalculation['tier']);
        $this->assertSame('2026-10-16 17:00:00', $firstCalculation['response']->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-19 16:00:00', $firstCalculation['resolution']->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s'));
        $this->assertEquals($firstCalculation, $secondCalculation);
        $this->assertEquals($creationEvent->occurred_at_utc, $ticket->events()->whereKey($creationEvent->id)->firstOrFail()->occurred_at_utc);
    }

    public function test_pre_cutover_ticket_without_complete_sla_history_has_no_new_policy_deadlines(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-15 17:00:00', 'Asia/Jakarta'));
        $requester = User::query()->create([
            'name' => 'Legacy requester',
            'email' => 'sla-'.Str::uuid().'@example.test',
            'password' => Hash::make('test-password'),
            'role' => 'employee',
            'department' => 'IT',
            'job_title' => 'Staff',
            'active' => true,
        ]);
        $ticket = app(TicketService::class)->create($requester, [
            'subject' => 'Pre-cutover ticket fixture',
            'description' => 'Only creation history is available.',
            'category' => 'Software',
            'priority' => 'urgent',
        ]);
        $creationEvent = $ticket->events()->where('event_type', 'ticket.created')->firstOrFail();

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-16 16:00:00', 'Asia/Jakarta'));
        $this->configureApprovedTargets();

        $this->assertSame(['ticket.created'], $ticket->events()->orderBy('event_ordinal')->pluck('event_type')->all());
        $this->assertNull(app(SlaDeadlineCalculator::class)->calculateFromTicketCreation(
            'urgent',
            $creationEvent->occurred_at_utc,
        ));
    }

    public function test_ticket_created_exactly_at_or_after_cutover_is_eligible(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-16 16:00:00', 'Asia/Jakarta'));
        $this->configureApprovedTargets();
        $effectiveAt = CarbonImmutable::parse(config('sla.effective_at'), 'UTC')->utc();
        $calculator = app(SlaDeadlineCalculator::class);

        $atCutover = $calculator->calculateFromTicketCreation('urgent', $effectiveAt);
        $afterCutover = $calculator->calculateFromTicketCreation('urgent', $effectiveAt->addSecond());

        $this->assertNotNull($atCutover);
        $this->assertNotNull($afterCutover);
    }

    public function test_ticket_created_on_saturday_gets_only_future_business_deadlines(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-17 10:00:00', 'Asia/Jakarta'));
        $this->configureApprovedTargets();
        $requester = User::query()->create([
            'name' => 'Saturday requester',
            'email' => 'sla-'.Str::uuid().'@example.test',
            'password' => Hash::make('test-password'),
            'role' => 'employee',
            'department' => 'IT',
            'job_title' => 'Staff',
            'active' => true,
        ]);

        $ticket = app(TicketService::class)->create($requester, [
            'subject' => 'Weekend ticket fixture',
            'description' => 'Ticket creation remains available on weekends.',
            'category' => 'Software',
            'priority' => 'urgent',
        ]);
        $created = $ticket->events()->where('event_type', 'ticket.created')->firstOrFail();
        $deadlines = app(SlaDeadlineCalculator::class)->calculateFromTicketCreation('urgent', $created->occurred_at_utc);

        $this->assertNotNull($deadlines);
        $this->assertSame('2026-10-17 10:00:00', $created->occurred_at_utc->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-19 09:30:00', $deadlines['response']->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-19 17:30:00', $deadlines['resolution']->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s'));
        $this->assertTrue($deadlines['response']->greaterThan($created->occurred_at_utc));
    }

    public function test_all_configured_priority_targets_use_eight_hour_business_days(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-19 08:30:00', 'Asia/Jakarta'));
        $this->configureApprovedTargets();
        $calculator = app(SlaDeadlineCalculator::class);
        $createdAt = CarbonImmutable::parse('2026-10-19 08:30:00', 'Asia/Jakarta');

        $expected = [
            'urgent' => ['2026-10-19 09:30:00', '2026-10-19 17:30:00'],
            'high' => ['2026-10-19 13:30:00', '2026-10-21 17:30:00'],
            'medium' => ['2026-10-19 17:30:00', '2026-10-23 17:30:00'],
            'low' => ['2026-10-20 17:30:00', '2026-10-30 17:30:00'],
        ];

        foreach ($expected as $priority => [$response, $resolution]) {
            $deadlines = $calculator->calculateFromTicketCreation($priority, $createdAt);

            $this->assertNotNull($deadlines);
            $this->assertSame($response, $deadlines['response']->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s'));
            $this->assertSame($resolution, $deadlines['resolution']->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s'));
        }
    }

    public function test_calculator_returns_no_partial_deadlines_when_calendar_coverage_ends_before_resolution(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-19 08:30:00', 'Asia/Jakarta'));
        $this->configureApprovedTargets();
        config([
            'sla.calendar.verified_through' => '2026-10-19',
            'sla.calendar.holidays.verified_through' => '2026-10-19',
        ]);

        $deadlines = app(SlaDeadlineCalculator::class)->calculateFromTicketCreation(
            'high',
            CarbonImmutable::parse('2026-10-19 08:30:00', 'Asia/Jakarta'),
        );

        $this->assertNull($deadlines);
    }

    public function test_both_unavailable_deadlines_produce_no_calculation_result(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-16 16:00:00', 'Asia/Jakarta'));
        $this->configureApprovedTargets();
        config([
            'sla.calendar.verified_through' => '2026-10-16',
            'sla.calendar.holidays.verified_through' => '2026-10-16',
        ]);

        $deadlines = app(SlaDeadlineCalculator::class)->calculateFromTicketCreation(
            'urgent',
            CarbonImmutable::parse('2026-10-19 08:30:00', 'Asia/Jakarta'),
        );

        $this->assertNull($deadlines);
    }

    private function configureApprovedTargets(): void
    {
        config([
            'sla.enabled' => true,
            'sla.policy_approved' => true,
            'sla.acceptance_approved' => true,
            'sla.effective_at' => '2026-10-16T00:00:00+00:00',
            'sla.priority_mapping' => [
                'urgent' => 'P1',
                'high' => 'P2',
                'medium' => 'P3',
                'low' => 'P4',
            ],
            'sla.calendar' => [
                'timezone' => 'Asia/Jakarta',
                'working_days' => [1, 2, 3, 4, 5],
                'work_windows' => [['08:30', '12:00'], ['13:00', '17:30']],
                'verified' => true,
                'schedule_version' => 'synthetic-schedule-v1',
                'verified_from' => '2026-01-01',
                'verified_through' => '2027-12-31',
                'holidays' => [
                    'source' => 'synthetic test fixture; not official holiday data',
                    'version' => 'synthetic-holidays-v1',
                    'verified' => true,
                    'verified_from' => '2026-01-01',
                    'verified_through' => '2027-12-31',
                    'dates' => [],
                ],
            ],
        ]);
    }
}
