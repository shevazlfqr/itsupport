<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Comment;
use App\Models\Ticket;
use App\Models\TicketRead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationReadTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_ticket_updates_existing_composite_read_record(): void
    {
        $employee = $this->account('employee');
        $ticket = $this->ticket($employee, 'Ticket detail read marker');
        $previousReadAt = now()->subHour();

        TicketRead::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $employee->id,
            'last_read_at' => $previousReadAt,
        ]);

        $this->actingAs($employee)
            ->followingRedirects()
            ->get(route('tickets.show', $ticket))
            ->assertOk()
            ->assertSee($ticket->subject);

        $read = TicketRead::query()
            ->where('ticket_id', $ticket->id)
            ->where('user_id', $employee->id)
            ->firstOrFail();

        $this->assertTrue($read->last_read_at->greaterThan($previousReadAt));
        $this->assertSame(1, TicketRead::query()->count());
    }

    public function test_comment_is_saved_and_existing_composite_read_record_is_updated(): void
    {
        $employee = $this->account('employee');
        $ticket = $this->ticket($employee, 'Ticket comment read marker');
        $previousReadAt = now()->subHour();
        $message = 'Informasi tambahan untuk pengecekan tiket.';

        TicketRead::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $employee->id,
            'last_read_at' => $previousReadAt,
        ]);

        $this->actingAs($employee)
            ->post(route('tickets.comments.store', $ticket), ['message' => $message])
            ->assertRedirect(route('tickets.show', $ticket));

        $this->assertDatabaseHas('comments', [
            'ticket_id' => $ticket->id,
            'user_id' => $employee->id,
            'message' => $message,
        ]);

        $read = TicketRead::query()
            ->where('ticket_id', $ticket->id)
            ->where('user_id', $employee->id)
            ->firstOrFail();

        $this->assertTrue($read->last_read_at->greaterThan($previousReadAt));
        $this->assertSame(1, TicketRead::query()->count());
    }

    public function test_employee_can_mark_all_notifications_read_only_for_tickets_they_can_view(): void
    {
        $employee = $this->account('employee');
        $otherEmployee = $this->account('employee');
        $technician = $this->account('it');
        $ownTicket = $this->ticket($employee, 'Employee ticket with unread activity');
        $otherTicket = $this->ticket($otherEmployee, 'Another employee ticket');
        $this->activity($ownTicket, $technician);
        $this->comment($ownTicket, $technician);
        $this->activity($otherTicket, $technician);
        TicketRead::query()->create([
            'ticket_id' => $ownTicket->id,
            'user_id' => $employee->id,
            'last_read_at' => now()->subHours(2),
        ]);
        $ownTicketUpdatedAt = $ownTicket->updated_at->toDateTimeString();
        $ownActivityCount = $ownTicket->activities()->count();
        $otherTicketUpdatedAt = $otherTicket->updated_at->toDateTimeString();

        $this->actingAs($employee)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Tandai semua dibaca')
            ->assertDontSee('Another employee ticket');

        $this->actingAs($employee)
            ->from(route('dashboard'))
            ->post(route('notifications.read-all'))
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('success', 'Semua notifikasi sudah ditandai dibaca.');

        $read = TicketRead::query()
            ->where('ticket_id', $ownTicket->id)
            ->where('user_id', $employee->id)
            ->first();

        $this->assertNotNull($read);
        $this->assertGreaterThan($this->activityTimestamp($ownTicket), $read->last_read_at->timestamp);
        $this->assertDatabaseMissing('ticket_reads', [
            'ticket_id' => $otherTicket->id,
            'user_id' => $employee->id,
        ]);

        $this->assertSame($ownTicketUpdatedAt, $ownTicket->fresh()->updated_at->toDateTimeString());
        $this->assertSame($ownActivityCount, $ownTicket->activities()->count());
        $this->assertSame($otherTicketUpdatedAt, $otherTicket->fresh()->updated_at->toDateTimeString());

        $this->actingAs($employee)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Semua sudah dibaca')
            ->assertDontSee('Tandai semua dibaca');
    }

    public function test_it_and_admin_can_mark_all_notifications_for_all_tickets_read(): void
    {
        $requester = $this->account('employee');
        $actor = $this->account('employee');
        $tickets = [
            $this->ticket($requester, 'First ticket'),
            $this->ticket($requester, 'Second ticket'),
        ];
        foreach ($tickets as $ticket) {
            $this->activity($ticket, $actor);
        }

        foreach (['it', 'admin'] as $role) {
            $user = $this->account($role);

            $this->actingAs($user)
                ->post(route('notifications.read-all'))
                ->assertRedirect();

            foreach ($tickets as $ticket) {
                $this->assertDatabaseHas('ticket_reads', [
                    'ticket_id' => $ticket->id,
                    'user_id' => $user->id,
                ]);
            }
        }
    }

    public function test_guest_cannot_mark_notifications_read(): void
    {
        $employee = $this->account('employee');
        $technician = $this->account('it');
        $ticket = $this->ticket($employee, 'Unread ticket');
        $this->activity($ticket, $technician);

        $this->post(route('notifications.read-all'))
            ->assertRedirect(route('login'));

        $this->assertDatabaseMissing('ticket_reads', [
            'ticket_id' => $ticket->id,
            'user_id' => $employee->id,
        ]);
    }

    private function account(string $role): User
    {
        return User::query()->create([
            'name' => 'Notification '.ucfirst($role),
            'email' => 'notification-'.Str::uuid().'@example.test',
            'password' => Hash::make('password'),
            'role' => $role,
            'department' => 'IT',
            'job_title' => 'Staff',
            'active' => true,
        ]);
    }

    private function ticket(User $requester, string $subject): Ticket
    {
        return Ticket::query()->create([
            'subject' => $subject,
            'description' => 'Notification read test fixture.',
            'category' => 'Perangkat',
            'priority' => 'medium',
            'status' => 'new',
            'requester_id' => $requester->id,
            'assignee_id' => null,
            'resolution_note' => '',
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDay(),
            'sla_deadline' => now()->addDay(),
        ]);
    }

    private function activity(Ticket $ticket, User $actor): Activity
    {
        return Activity::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $actor->id,
            'message' => 'Status changed for notification test.',
            'created_at' => now()->subMinute(),
        ]);
    }

    private function comment(Ticket $ticket, User $actor): Comment
    {
        return Comment::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $actor->id,
            'message' => 'A reply for the notification read test.',
            'created_at' => now()->subSeconds(30),
        ]);
    }

    private function activityTimestamp(Ticket $ticket): int
    {
        return $ticket->activities()->firstOrFail()->created_at->timestamp;
    }
}
