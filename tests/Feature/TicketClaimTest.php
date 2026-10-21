<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class TicketClaimTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_claim_an_unassigned_new_ticket_and_records_one_activity(): void
    {
        $requester = $this->account('employee');
        $technician = $this->account('it');
        $ticket = $this->ticket($requester);

        $this->actingAs($technician)
            ->post(route('tickets.claim', $ticket))
            ->assertRedirect(route('tickets.show', $ticket))
            ->assertSessionHas('success');

        $ticket->refresh();
        $this->assertSame('assigned', $ticket->status);
        $this->assertSame($technician->id, $ticket->assignee_id);
        $this->assertSame(1, $ticket->activities()->count());
        $activity = $ticket->activities()->firstOrFail();
        $this->assertSame($technician->id, $activity->user_id);
        $this->assertSame('Mengambil ticket dan menjadi teknisi yang ditugaskan', $activity->message);
    }

    public function test_employee_and_admin_cannot_use_the_technician_claim_route(): void
    {
        $requester = $this->account('employee');
        $ticket = $this->ticket($requester);

        foreach (['employee', 'admin'] as $role) {
            $actor = $role === 'employee' ? $requester : $this->account($role);

            $this->actingAs($actor)
                ->post(route('tickets.claim', $ticket))
                ->assertForbidden();

            $this->actingAs($actor)
                ->post('/', [
                    'action' => 'claim_ticket',
                    'ticket_id' => $ticket->id,
                ])
                ->assertForbidden();
        }

        $this->assertSame('new', $ticket->fresh()->status);
        $this->assertNull($ticket->fresh()->assignee_id);
        $this->assertSame(0, $ticket->activities()->count());
    }

    public function test_an_already_assigned_ticket_cannot_be_claimed_by_another_technician(): void
    {
        $requester = $this->account('employee');
        $currentAssignee = $this->account('it');
        $otherTechnician = $this->account('it');
        $ticket = $this->ticket($requester, 'assigned', $currentAssignee);
        $beforeUpdatedAt = $ticket->updated_at->toDateTimeString();

        $this->actingAs($otherTechnician)
            ->post(route('tickets.claim', $ticket))
            ->assertForbidden();

        $ticket->refresh();
        $this->assertSame('assigned', $ticket->status);
        $this->assertSame($currentAssignee->id, $ticket->assignee_id);
        $this->assertSame($beforeUpdatedAt, $ticket->updated_at->toDateTimeString());
        $this->assertSame(0, $ticket->activities()->count());
    }

    public function test_closed_and_cancelled_tickets_are_rejected_without_side_effects(): void
    {
        $technician = $this->account('it');

        foreach (['closed', 'cancelled'] as $status) {
            $requester = $this->account('employee');
            $ticket = $this->ticket($requester, $status);
            $this->activity($ticket, $requester, 'Existing history');
            $beforeUpdatedAt = $ticket->fresh()->updated_at->toDateTimeString();
            $beforeActivities = $ticket->activities()->get(['id', 'user_id', 'message', 'created_at'])->toArray();

            $this->actingAs($technician)
                ->post(route('tickets.claim', $ticket))
                ->assertRedirect()
                ->assertSessionHas('error', 'Status ticket ini tidak memungkinkan claim.');

            $ticket->refresh();
            $this->assertSame($status, $ticket->status);
            $this->assertNull($ticket->assignee_id);
            $this->assertSame($beforeUpdatedAt, $ticket->updated_at->toDateTimeString());
            $this->assertSame(
                $beforeActivities,
                $ticket->activities()->get(['id', 'user_id', 'message', 'created_at'])->toArray(),
            );

            $this->assertFalse(app(TicketService::class)->claim($ticket, $technician));
            $this->assertSame($status, $ticket->fresh()->status);
            $this->assertNull($ticket->fresh()->assignee_id);
            $this->assertSame($beforeUpdatedAt, $ticket->fresh()->updated_at->toDateTimeString());
            $this->assertSame(count($beforeActivities), $ticket->activities()->count());
        }
    }

    public function test_legacy_claim_action_uses_the_same_terminal_status_guard(): void
    {
        $requester = $this->account('employee');
        $technician = $this->account('it');
        $ticket = $this->ticket($requester, 'closed');

        $this->actingAs($technician)
            ->post('/', [
                'action' => 'claim_ticket',
                'ticket_id' => $ticket->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'Status ticket ini tidak memungkinkan claim.');

        $this->assertSame('closed', $ticket->fresh()->status);
        $this->assertNull($ticket->fresh()->assignee_id);
        $this->assertSame(0, $ticket->activities()->count());
    }

    public function test_service_rejects_callers_without_the_technician_role(): void
    {
        $requester = $this->account('employee');
        $employee = $this->account('employee');
        $ticket = $this->ticket($requester);

        try {
            app(TicketService::class)->claim($ticket, $employee);
            $this->fail('A non-technician caller should not claim a ticket through the service.');
        } catch (AuthorizationException) {
            $this->assertSame('new', $ticket->fresh()->status);
            $this->assertNull($ticket->fresh()->assignee_id);
            $this->assertSame(0, $ticket->activities()->count());
        }
    }

    public function test_a_second_stale_claim_cannot_overwrite_the_first_assignment(): void
    {
        $requester = $this->account('employee');
        $firstTechnician = $this->account('it');
        $secondTechnician = $this->account('it');
        $ticket = $this->ticket($requester);
        $firstSnapshot = Ticket::query()->findOrFail($ticket->id);
        $staleSnapshot = Ticket::query()->findOrFail($ticket->id);
        $service = app(TicketService::class);

        $this->assertTrue($service->claim($firstSnapshot, $firstTechnician));
        $updatedAtAfterWinner = $ticket->fresh()->updated_at->toDateTimeString();
        $this->assertFalse($service->claim($staleSnapshot, $secondTechnician));

        $ticket->refresh();
        $this->assertSame('assigned', $ticket->status);
        $this->assertSame($firstTechnician->id, $ticket->assignee_id);
        $this->assertSame($updatedAtAfterWinner, $ticket->updated_at->toDateTimeString());
        $this->assertSame(1, $ticket->activities()->count());
        $this->assertSame($firstTechnician->id, $ticket->activities()->firstOrFail()->user_id);
    }

    public function test_controller_reports_a_claim_conflict_without_mutating_the_ticket(): void
    {
        $requester = $this->account('employee');
        $technician = $this->account('it');
        $ticket = $this->ticket($requester);
        $beforeUpdatedAt = $ticket->updated_at->toDateTimeString();

        $service = \Mockery::mock(TicketService::class);
        $service->shouldReceive('claim')->once()->andReturn(false);
        $this->app->instance(TicketService::class, $service);

        $this->actingAs($technician)
            ->post(route('tickets.claim', $ticket))
            ->assertRedirect()
            ->assertSessionHas('error', 'Ticket berubah atau baru saja diambil teknisi lain. Muat ulang halaman.');

        $this->assertSame('new', $ticket->fresh()->status);
        $this->assertNull($ticket->fresh()->assignee_id);
        $this->assertSame($beforeUpdatedAt, $ticket->fresh()->updated_at->toDateTimeString());
        $this->assertSame(0, $ticket->activities()->count());
    }

    private function account(string $role): User
    {
        return User::query()->create([
            'name' => 'Test '.ucfirst($role),
            'email' => 'claim-'.Str::uuid().'@example.test',
            'password' => Hash::make('password'),
            'role' => $role,
            'department' => 'IT',
            'job_title' => 'Staff',
            'active' => true,
        ]);
    }

    private function ticket(User $requester, string $status = 'new', ?User $assignee = null): Ticket
    {
        return Ticket::query()->create([
            'subject' => 'Printer issue',
            'description' => 'The shared printer is unavailable.',
            'category' => 'Perangkat',
            'priority' => 'medium',
            'status' => $status,
            'requester_id' => $requester->id,
            'assignee_id' => $assignee?->id,
            'resolution_note' => '',
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDay(),
            'sla_deadline' => now()->addDay(),
        ]);
    }

    private function activity(Ticket $ticket, User $actor, string $message): Activity
    {
        return Activity::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $actor->id,
            'message' => $message,
            'created_at' => now()->subHours(2),
        ]);
    }
}
