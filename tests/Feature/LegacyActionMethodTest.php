<?php

namespace Tests\Feature;

use App\Http\Controllers\LegacyActionController;
use App\Http\Middleware\LegacyCsrfToken;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

class LegacyActionMethodTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_cannot_run_any_legacy_mutation_and_leaves_application_state_unchanged(): void
    {
        $requester = $this->account('employee');
        $technician = $this->account('it');
        $admin = $this->account('admin');
        $newTicket = $this->ticket($requester, 'new');
        $assignedTicket = $this->ticket($requester, 'assigned', $technician);
        $completedTicket = $this->ticket($requester, 'completed', $technician);

        $cases = [
            ['login', null, ['email' => $requester->email, 'password' => 'Valid-password1!']],
            ['logout', $requester, []],
            ['create_user', $admin, [
                'name' => 'New Employee',
                'email' => 'legacy-new-'.Str::uuid().'@example.test',
                'role' => 'employee',
                'job_title' => 'Staff',
                'department' => 'Operations',
                'password' => 'Valid-password1!',
            ]],
            ['update_user', $admin, [
                'user_id' => $requester->id,
                'name' => 'Changed Requester',
                'email' => 'changed-'.Str::uuid().'@example.test',
                'role' => 'employee',
                'job_title' => 'Changed role title',
                'department' => 'Changed department',
            ]],
            ['toggle_user', $admin, ['user_id' => $requester->id]],
            ['create_ticket', $requester, [
                'subject' => 'A valid legacy ticket request',
                'description' => 'This request is valid but must not be created on GET.',
                'category' => 'Perangkat',
                'priority' => 'medium',
            ]],
            ['comment', $requester, [
                'ticket_id' => $newTicket->id,
                'message' => 'A valid legacy comment that must not be saved.',
            ]],
            ['claim_ticket', $technician, ['ticket_id' => $newTicket->id]],
            ['update_ticket', $admin, [
                'ticket_id' => $assignedTicket->id,
                'status' => 'in_progress',
                'assignee_id' => $technician->id,
                'resolution_note' => '',
            ]],
            ['requester_resolution', $requester, [
                'ticket_id' => $completedTicket->id,
                'decision' => 'close',
            ]],
        ];

        foreach ($cases as [$action, $actor, $parameters]) {
            if ($actor instanceof User) {
                $this->actingAs($actor);
                $this->assertAuthenticatedAs($actor);
            } else {
                auth()->logout();
                $this->assertGuest();
            }

            $before = $this->applicationStateSnapshot();
            $query = http_build_query(['action' => $action, ...$parameters]);
            $response = $this->get('/index.php?'.$query);

            $this->assertSame(405, $response->getStatusCode(), "GET action [{$action}] must be rejected.");
            $this->assertSame($before, $this->applicationStateSnapshot(), "GET action [{$action}] changed persisted state.");

            if ($actor instanceof User) {
                $this->assertSame(
                    (int) $actor->id,
                    (int) auth()->id(),
                    "GET action [{$action}] changed the authenticated user.",
                );
            } else {
                $this->assertTrue(auth()->guest(), "GET action [{$action}] authenticated a user.");
            }
        }
    }

    public function test_read_only_legacy_get_routes_remain_available(): void
    {
        $admin = $this->account('admin');
        $requester = $this->account('employee');
        $this->ticket($requester, 'new');
        $before = $this->applicationStateSnapshot();

        $this->actingAs($admin)
            ->get('/index.php?view=dashboard')
            ->assertOk();
        $this->assertSame($before, $this->applicationStateSnapshot());

        $this->actingAs($admin)
            ->get('/index.php?api=updates')
            ->assertOk()
            ->assertJsonStructure(['changed', 'events', 'server_time']);
        $this->assertSame($before, $this->applicationStateSnapshot());

        $this->actingAs($admin)
            ->get('/index.php?export=csv&type=tickets')
            ->assertOk();
        $this->assertSame($before, $this->applicationStateSnapshot());
    }

    public function test_legacy_action_controller_also_rejects_direct_non_post_invocation(): void
    {
        $before = $this->applicationStateSnapshot();
        $request = Request::create('/index.php', 'GET', ['action' => 'create_ticket']);

        try {
            app(LegacyActionController::class)($request);
            $this->fail('The legacy dispatcher must reject direct non-POST invocation.');
        } catch (HttpExceptionInterface $exception) {
            $this->assertSame(405, $exception->getStatusCode());
        }

        $this->assertSame($before, $this->applicationStateSnapshot());
    }

    public function test_legacy_post_action_remains_supported_and_requires_csrf(): void
    {
        $requester = $this->account('employee');
        $payload = [
            'action' => 'create_ticket',
            'subject' => 'Valid legacy ticket request',
            'description' => 'This request verifies the supported POST path.',
            'category' => 'Perangkat',
            'priority' => 'medium',
        ];
        $before = $this->applicationStateSnapshot();

        $this->enforceCsrfDuringThisTest();

        $this->actingAs($requester)
            ->post('/index.php', $payload)
            ->assertStatus(419);
        $this->assertSame($before, $this->applicationStateSnapshot());

        $token = Str::random(40);
        $this->withSession(['_token' => $token])
            ->actingAs($requester)
            ->post('/index.php', ['csrf' => $token, ...$payload])
            ->assertRedirect();

        $this->assertDatabaseHas('tickets', [
            'requester_id' => $requester->id,
            'subject' => $payload['subject'],
        ]);
    }

    private function enforceCsrfDuringThisTest(): void
    {
        $this->app->bind(LegacyCsrfToken::class, function ($app): LegacyCsrfToken {
            return new class($app, $app->make(Encrypter::class)) extends LegacyCsrfToken
            {
                protected function runningUnitTests()
                {
                    return false;
                }
            };
        });
    }

    private function account(string $role): User
    {
        return User::query()->create([
            'name' => 'Legacy '.$role,
            'email' => 'legacy-'.Str::uuid().'@example.test',
            'password' => Hash::make('Valid-password1!'),
            'role' => $role,
            'department' => 'IT',
            'job_title' => 'Staff',
            'active' => true,
        ]);
    }

    private function ticket(User $requester, string $status, ?User $assignee = null): Ticket
    {
        return Ticket::query()->create([
            'subject' => 'Legacy '.$status.' ticket',
            'description' => 'Ticket fixture for legacy method validation.',
            'category' => 'Perangkat',
            'priority' => 'medium',
            'status' => $status,
            'requester_id' => $requester->id,
            'assignee_id' => $assignee?->id,
            'resolution_note' => '',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subHour(),
            'sla_deadline' => now()->addDay(),
        ]);
    }

    private function applicationStateSnapshot(): array
    {
        $snapshot = [];

        foreach (['users', 'tickets', 'comments', 'activities', 'attachments', 'ticket_reads', 'ticket_events'] as $table) {
            $rows = DB::table($table)
                ->get()
                ->map(static fn (object $row): array => (array) $row)
                ->all();

            usort($rows, static fn (array $left, array $right): int => strcmp(serialize($left), serialize($right)));
            $snapshot[$table] = $rows;
        }

        return $snapshot;
    }
}
