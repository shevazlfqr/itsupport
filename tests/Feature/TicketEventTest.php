<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\User;
use App\Services\SlaKpiFeatureGate;
use App\Services\TicketEventRecorder;
use App\Services\TicketService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class TicketEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_creation_and_public_comments_record_auditable_events(): void
    {
        $requester = $this->account('employee');
        $technician = $this->account('it');
        $ticket = $this->createTicket($requester);
        $created = $ticket->events()->firstOrFail();

        $this->assertSame('ticket.created', $created->event_type);
        $this->assertSame('ticket.create', $created->source);
        $this->assertSame($requester->id, $created->actor_id);
        $this->assertSame('employee', $created->actor_role_snapshot);
        $this->assertSame('new', $created->to_status);
        $this->assertSame('medium', $created->to_priority);
        $this->assertSame('UTC', $created->occurred_at_utc->timezoneName);
        $this->assertSame('UTC', $created->recorded_at_utc->timezoneName);
        $this->assertTrue($ticket->created_at->utc()->equalTo($created->occurred_at_utc));

        $comment = app(TicketService::class)->addComment($ticket, $technician, 'Saya sedang memeriksa koneksi printer.');
        $commentEvent = $ticket->events()->where('comment_id', $comment->id)->firstOrFail();

        $this->assertSame('ticket.public_comment_added', $commentEvent->event_type);
        $this->assertSame($technician->id, $commentEvent->actor_id);
        $this->assertSame('it', $commentEvent->actor_role_snapshot);
        $this->assertSame($comment->id, $commentEvent->comment_id);
        $this->assertDatabaseMissing('ticket_events', ['ticket_id' => $ticket->id, 'event_type' => 'ticket.first_public_it_response']);
    }

    public function test_claim_records_one_state_event_and_a_stale_losing_claim_records_none(): void
    {
        $requester = $this->account('employee');
        $winner = $this->account('it');
        $loser = $this->account('it');
        $ticket = $this->createTicket($requester);
        $firstSnapshot = Ticket::query()->findOrFail($ticket->id);
        $staleSnapshot = Ticket::query()->findOrFail($ticket->id);
        $service = app(TicketService::class);

        $this->assertTrue($service->claim($firstSnapshot, $winner));
        $this->assertFalse($service->claim($staleSnapshot, $loser));

        $claimEvents = $ticket->events()->where('source', 'ticket.claim')->get();
        $this->assertCount(1, $claimEvents);
        $this->assertSame('new', $claimEvents->first()->from_status);
        $this->assertSame('assigned', $claimEvents->first()->to_status);
        $this->assertSame($winner->id, $claimEvents->first()->to_assignee_id);
        $this->assertSame($winner->id, $ticket->fresh()->assignee_id);
    }

    public function test_resolution_reopen_and_final_resolution_keep_each_lifecycle_event(): void
    {
        $requester = $this->account('employee');
        $technician = $this->account('it');
        $ticket = $this->createTicket($requester);
        $service = app(TicketService::class);

        $this->assertTrue($service->claim($ticket, $technician));
        $ticket = $ticket->fresh();
        $service->update($ticket, $technician, 'in_progress', $technician->id, '');
        $service->update($ticket->fresh(), $technician, 'completed', $technician->id, 'Printer driver reinstalled.');
        $firstResolution = $ticket->events()->where('event_type', 'ticket.first_resolution')->firstOrFail();

        $service->resolveByRequester($ticket->fresh(), $requester, 'reopen');
        $service->update($ticket->fresh(), $technician, 'completed', $technician->id, 'Cable replaced and tested.');
        $finalResolution = $ticket->events()->where('event_type', 'ticket.final_resolution')->firstOrFail();
        $service->resolveByRequester($ticket->fresh(), $requester, 'close');

        $this->assertSame(1, $firstResolution->resolution_cycle);
        $this->assertSame(2, $finalResolution->resolution_cycle);
        $this->assertSame('in_progress', $ticket->events()->where('event_type', 'ticket.reopened')->value('to_status'));
        $this->assertSame(1, $ticket->events()->where('event_type', 'ticket.reopened')->value('resolution_cycle'));
        $this->assertSame(2, $ticket->events()->where('event_type', 'ticket.closed')->value('resolution_cycle'));
        $this->assertSame('closed', $ticket->fresh()->status);
        $this->assertSame(1, $ticket->events()->where('event_type', 'ticket.first_resolution')->count());
        $this->assertSame(1, $ticket->events()->where('event_type', 'ticket.final_resolution')->count());
    }

    public function test_cancelled_ticket_records_a_cancellation_not_a_resolution(): void
    {
        $requester = $this->account('employee');
        $technician = $this->account('it');
        $ticket = $this->createTicket($requester);
        $service = app(TicketService::class);
        $service->claim($ticket, $technician);
        $service->update($ticket->fresh(), $technician, 'in_progress', $technician->id, '');
        $service->update($ticket->fresh(), $technician, 'cancelled', $technician->id, '');

        $this->assertSame(1, $ticket->events()->where('event_type', 'ticket.cancelled')->count());
        $this->assertSame(0, $ticket->events()->whereIn('event_type', ['ticket.first_resolution', 'ticket.final_resolution'])->count());
        $this->assertSame('cancelled', $ticket->fresh()->status);
    }

    public function test_service_rejects_unauthorized_and_invalid_transitions_without_partial_events(): void
    {
        $requester = $this->account('employee');
        $otherTechnician = $this->account('it');
        $assignee = $this->account('it');
        $ticket = $this->createTicket($requester);
        $service = app(TicketService::class);

        try {
            $service->update($ticket, $otherTechnician, 'completed', $assignee->id, 'Selesai diperbaiki.');
            $this->fail('A non-assignee technician must not update the ticket.');
        } catch (AuthorizationException) {
            $this->assertSame('new', $ticket->fresh()->status);
            $this->assertSame(1, $ticket->events()->count());
        }

        $this->assertTrue($service->claim($ticket, $assignee));
        try {
            $service->update($ticket->fresh(), $assignee, 'closed', $assignee->id, '');
            $this->fail('A transition absent from the configured map must be rejected by the service.');
        } catch (ValidationException) {
            $this->assertSame('assigned', $ticket->fresh()->status);
            $this->assertSame(2, $ticket->events()->count());
        }
    }

    public function test_legacy_ticket_resolution_is_recorded_without_fabricating_a_first_resolution(): void
    {
        $requester = $this->account('employee');
        $technician = $this->account('it');
        $ticket = Ticket::query()->create([
            'subject' => 'Legacy ticket',
            'description' => 'Ticket created before event capture.',
            'category' => 'Software',
            'priority' => 'medium',
            'status' => 'in_progress',
            'requester_id' => $requester->id,
            'assignee_id' => $technician->id,
            'resolution_note' => '',
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDay(),
            'sla_deadline' => now()->addDay(),
        ]);

        app(TicketService::class)->update($ticket, $technician, 'completed', $technician->id, 'Legacy work now completed.');

        $this->assertSame(1, $ticket->events()->where('event_type', 'ticket.resolution_recorded')->count());
        $this->assertSame(0, $ticket->events()->where('event_type', 'ticket.first_resolution')->count());
        $this->assertNull($ticket->events()->firstOrFail()->resolution_cycle);
    }

    public function test_duplicate_event_key_is_idempotent_for_the_same_payload_and_rejects_conflicting_payload(): void
    {
        $requester = $this->account('employee');
        $ticket = $this->createTicket($requester);
        $event = $ticket->events()->firstOrFail();

        $replayed = app(TicketEventRecorder::class)->record(
            $ticket,
            'ticket.created',
            'ticket.created',
            $requester,
            'ticket.create',
            $event->occurred_at_utc,
            ['to_status' => 'new', 'to_priority' => 'medium'],
        );

        $this->assertSame($event->id, $replayed->id);
        $this->assertSame(1, $ticket->events()->count());

        try {
            app(TicketEventRecorder::class)->record(
                $ticket,
                'ticket.created',
                'ticket.created',
                $this->account('admin'),
                'ticket.create',
                $event->occurred_at_utc,
                ['to_status' => 'new', 'to_priority' => 'medium'],
            );
            $this->fail('Reusing an event key for a different actor must fail.');
        } catch (LogicException) {
            $this->assertSame(1, $ticket->events()->count());
        }
    }

    public function test_event_rows_cannot_be_updated_or_deleted_through_the_model(): void
    {
        $ticket = $this->createTicket($this->account('employee'));
        $event = $ticket->events()->firstOrFail();

        try {
            $event->update(['reason' => 'changed']);
            $this->fail('An audit event must not be updated.');
        } catch (LogicException) {
            $this->assertDatabaseHas('ticket_events', ['id' => $event->id, 'reason' => null]);
        }

        try {
            $event->delete();
            $this->fail('An audit event must not be deleted.');
        } catch (LogicException) {
            $this->assertDatabaseHas('ticket_events', ['id' => $event->id]);
        }
    }

    public function test_sla_kpi_and_calendar_remain_disabled_without_approval(): void
    {
        $this->assertFalse(config('sla.enabled'));
        $this->assertFalse(config('sla.policy_approved'));
        $this->assertSame([], config('sla.priority_mapping'));
        $this->assertFalse(config('sla.calendar.verified'));
        $this->assertFalse(app(SlaKpiFeatureGate::class)->canPublish());

        config([
            'sla.enabled' => true,
            'sla.policy_approved' => true,
            'sla.acceptance_approved' => true,
            'sla.effective_at' => now('UTC')->subMinute()->toIso8601String(),
        ]);

        $this->assertFalse(app(SlaKpiFeatureGate::class)->canPublish());
    }

    public function test_event_insert_failure_rolls_back_the_ticket_state_and_activity(): void
    {
        $requester = $this->account('employee');
        $technician = $this->account('it');
        $ticket = $this->createTicket($requester);
        DB::statement("CREATE TRIGGER reject_ticket_event BEFORE INSERT ON ticket_events BEGIN SELECT RAISE(ABORT, 'blocked'); END");

        try {
            app(TicketService::class)->claim($ticket, $technician);
            $this->fail('The event insert trigger should reject the claim transaction.');
        } catch (QueryException) {
            $this->assertSame('new', $ticket->fresh()->status);
            $this->assertNull($ticket->fresh()->assignee_id);
            $this->assertSame(1, $ticket->events()->count());
            $this->assertSame(1, $ticket->activities()->count());
        }
    }

    public function test_event_insert_failure_rolls_back_ticket_creation(): void
    {
        DB::statement("CREATE TRIGGER reject_ticket_event BEFORE INSERT ON ticket_events BEGIN SELECT RAISE(ABORT, 'blocked'); END");

        try {
            $this->createTicket($this->account('employee'));
            $this->fail('The event insert trigger should reject ticket creation.');
        } catch (QueryException) {
            $this->assertSame(0, Ticket::query()->count());
            $this->assertSame(0, TicketEvent::query()->count());
        }
    }

    private function createTicket(User $requester): Ticket
    {
        return app(TicketService::class)->create($requester, [
            'subject' => 'Printer issue',
            'description' => 'The shared printer is unavailable.',
            'category' => 'Perangkat',
            'priority' => 'medium',
        ]);
    }

    private function account(string $role): User
    {
        return User::query()->create([
            'name' => 'Test '.ucfirst($role),
            'email' => $role.'-'.Str::uuid().'@example.test',
            'password' => Hash::make('password'),
            'role' => $role,
            'department' => 'IT',
            'job_title' => 'Staff',
            'active' => true,
        ]);
    }
}
