<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DemoTicketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class DemoTicketSeederTest extends TestCase
{
    use RefreshDatabase;

    private string $originalTimezone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalTimezone = date_default_timezone_get();
        config(['app.timezone' => 'Asia/Jakarta']);
        date_default_timezone_set('Asia/Jakarta');
        Carbon::setTestNow(Carbon::parse('2026-10-16 15:00:00', 'Asia/Jakarta'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        date_default_timezone_set($this->originalTimezone);

        parent::tearDown();
    }

    public function test_it_seeds_a_varied_dataset_without_changing_accounts_and_is_idempotent(): void
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());

        $this->account('employee');
        $this->account('it');
        $this->account('it');
        $this->account('admin');
        $this->account('employee', false);
        $accountSnapshot = hash('sha256', DB::table('users')->orderBy('id')->get()->toJson());

        $this->seed(DemoTicketSeeder::class);

        $this->assertDatabaseCount('tickets', 96);
        $this->assertDatabaseCount('attachments', 0);
        $this->assertSame($accountSnapshot, hash('sha256', DB::table('users')->orderBy('id')->get()->toJson()));

        foreach (array_keys(config('helpdesk.statuses')) as $status) {
            $this->assertSame(16, Ticket::query()->where('status', $status)->count());
        }

        foreach (array_keys(config('helpdesk.priorities')) as $priority) {
            $this->assertSame(24, Ticket::query()->where('priority', $priority)->count());
        }

        foreach (config('helpdesk.categories') as $category) {
            $this->assertSame(16, Ticket::query()->where('category', $category)->count());
        }

        $this->assertSame(16, Ticket::query()->whereNull('assignee_id')->count());
        $this->assertSame(80, Ticket::query()->whereNotNull('assignee_id')->count());
        $this->assertSame(0, Ticket::query()->whereIn('status', ['completed', 'closed'])->whereRaw("resolution_note = ''")->count());
        $this->assertSame(32, Ticket::query()->whereIn('status', ['completed', 'closed'])->where('resolution_note', '!=', '')->count());
        $this->assertSame(0, Ticket::query()->whereNotIn('status', ['completed', 'closed'])->where('resolution_note', '!=', '')->count());
        $this->assertSame(96, Ticket::query()->whereBetween('created_at', [
            Carbon::parse('2026-07-18 00:00:00', 'Asia/Jakarta'),
            Carbon::parse('2026-10-16 15:00:00', 'Asia/Jakarta'),
        ])->count());
        $this->assertGreaterThan(96, DB::table('comments')->count());
        $this->assertGreaterThan(96, DB::table('activities')->count());
        $this->assertGreaterThan(0, DB::table('ticket_reads')->count());
        $this->assertSame(0, DB::table('tickets as t')->leftJoin('users as u', 'u.id', '=', 't.requester_id')->whereNull('u.id')->count());
        $this->assertSame(0, DB::table('tickets as t')->leftJoin('users as u', 'u.id', '=', 't.assignee_id')->whereNotNull('t.assignee_id')->whereNull('u.id')->count());
        $this->assertSame(0, DB::table('comments as c')->leftJoin('tickets as t', 't.id', '=', 'c.ticket_id')->whereNull('t.id')->count());
        $this->assertSame(0, DB::table('comments as c')->leftJoin('users as u', 'u.id', '=', 'c.user_id')->whereNull('u.id')->count());
        $this->assertSame(DB::table('activities')->count(), DB::table('ticket_events')->count());

        $employee = User::query()->where('role', 'employee')->where('active', true)->firstOrFail();
        $sampleTicket = Ticket::query()->where('requester_id', $employee->id)->firstOrFail();
        $this->actingAs($employee)
            ->get(route('dashboard', ['view' => 'tickets']))
            ->assertOk()
            ->assertSee('[DEMO-IT-v1-');
        $this->get(route('tickets.show', $sampleTicket))
            ->assertRedirect(route('dashboard', ['ticket' => $sampleTicket->id]));
        $this->get(route('dashboard', ['ticket' => $sampleTicket->id]))
            ->assertOk()
            ->assertSee($sampleTicket->subject)
            ->assertSee('data demo it support v1 untuk pengujian.');

        $admin = User::query()->where('role', 'admin')->where('active', true)->firstOrFail();
        $this->actingAs($admin)
            ->get(route('dashboard', ['view' => 'analytics']))
            ->assertOk()
            ->assertSee('Analitik layanan IT');

        $export = $this->actingAs($admin)
            ->get(route('tickets.export', ['type' => 'tickets', 'range' => 90]))
            ->assertOk()
            ->streamedContent();
        $rows = $this->parseCsv($export);
        $this->assertCount(11, $rows[0]);
        $this->assertCount(96, $rows);
        $this->assertContains("[DEMO-IT-v1-002] Akses folder, \"Proyek Inti\"\nperlu diperiksa", array_column(array_slice($rows, 1), 1));

        $beforeRerun = [
            'tickets' => DB::table('tickets')->count(),
            'comments' => DB::table('comments')->count(),
            'activities' => DB::table('activities')->count(),
            'reads' => DB::table('ticket_reads')->count(),
            'events' => DB::table('ticket_events')->count(),
        ];

        $this->seed(DemoTicketSeeder::class);

        $this->assertSame($beforeRerun, [
            'tickets' => DB::table('tickets')->count(),
            'comments' => DB::table('comments')->count(),
            'activities' => DB::table('activities')->count(),
            'reads' => DB::table('ticket_reads')->count(),
            'events' => DB::table('ticket_events')->count(),
        ]);
        $this->assertSame($accountSnapshot, hash('sha256', DB::table('users')->orderBy('id')->get()->toJson()));
    }

    public function test_it_refuses_to_claim_a_preexisting_record_with_the_demo_prefix(): void
    {
        $requester = $this->account('employee');
        $this->account('it');
        Ticket::query()->create([
            'subject' => '[DEMO-IT-v1-001] "Printer, ruang rapat" gagal mencetak',
            'description' => 'Preexisting record without the seeder provenance activity.',
            'category' => 'Perangkat',
            'priority' => 'low',
            'status' => 'new',
            'requester_id' => $requester->id,
            'assignee_id' => null,
            'resolution_note' => '',
            'created_at' => now(),
            'updated_at' => now(),
            'sla_deadline' => now()->addHours(72),
        ]);

        try {
            $this->seed(DemoTicketSeeder::class);
            $this->fail('Seeder should reject an unowned demo-prefix collision.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('tidak memiliki penanda asal seeder', $exception->getMessage());
        }

        $this->assertDatabaseCount('tickets', 1);
        $this->assertDatabaseCount('activities', 0);
        $this->assertDatabaseCount('users', 2);
    }

    private function account(string $role, bool $active = true): User
    {
        return User::query()->create([
            'name' => 'Demo Seeder '.ucfirst($role),
            'email' => 'demo-seeder-'.Str::uuid().'@example.test',
            'password' => Hash::make('test-only-password'),
            'role' => $role,
            'department' => 'IT',
            'job_title' => 'Staff',
            'active' => $active,
        ]);
    }

    private function parseCsv(string $content): array
    {
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content);
        rewind($stream);
        $rows = [];

        while (($row = fgetcsv($stream)) !== false) {
            $rows[] = $row;
        }

        fclose($stream);

        return $rows;
    }
}
