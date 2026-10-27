<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AnalyticsExportTest extends TestCase
{
    use RefreshDatabase;

    private string $originalTimezone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalTimezone = date_default_timezone_get();
        config(['app.timezone' => 'Asia/Jakarta']);
        date_default_timezone_set('Asia/Jakarta');
        Carbon::setTestNow(Carbon::parse('2026-10-16 10:15:00', 'Asia/Jakarta'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        date_default_timezone_set($this->originalTimezone);

        parent::tearDown();
    }

    public function test_only_it_and_admin_can_download_either_export(): void
    {
        $employee = $this->account('employee');
        $it = $this->account('it');
        $admin = $this->account('admin');
        $this->ticket($employee, ['created_at' => Carbon::parse('2026-10-16 09:00:00')]);

        $this->get(route('tickets.export'))->assertRedirect('/login');
        $this->get(route('tickets.export', ['type' => 'statistics', 'range' => 7]))->assertRedirect('/login');

        foreach ([$it, $admin] as $privilegedUser) {
            foreach ([
                ['type' => 'tickets', 'range' => 7, 'view' => 'analytics'],
                ['type' => 'statistics', 'range' => 7, 'view' => 'analytics'],
            ] as $query) {
                $response = $this->actingAs($privilegedUser)
                    ->get(route('tickets.export', $query))
                    ->assertOk()
                    ->assertHeader('content-type', 'text/csv; charset=UTF-8');

                $this->assertStringContainsString('attachment;', (string) $response->headers->get('content-disposition'));
                $this->assertStringContainsString('private', (string) $response->headers->get('cache-control'));
                $this->assertStringContainsString('no-store', (string) $response->headers->get('cache-control'));
                $this->assertStringContainsString("\xEF\xBB\xBF", $response->streamedContent());
            }
        }

        foreach ([$employee] as $unauthorizedUser) {
            $this->actingAs($unauthorizedUser)
                ->get(route('tickets.export', ['range' => 7]))
                ->assertForbidden();
            $this->actingAs($unauthorizedUser)
                ->get(route('tickets.export', ['type' => 'statistics', 'range' => 7]))
                ->assertForbidden();
        }
    }

    public function test_ticket_export_uses_inclusive_local_calendar_ranges_for_7_30_and_90_days(): void
    {
        $it = $this->account('it');
        $requester = $this->account('employee');
        $today = Carbon::parse('2026-10-16 00:00:00', 'Asia/Jakarta');
        $allTickets = [];

        foreach ([7, 30, 90] as $days) {
            $start = $today->copy()->subDays($days - 1);
            $allTickets[] = $this->ticket($requester, ['subject' => "Range {$days} start", 'created_at' => $start]);
            $allTickets[] = $this->ticket($requester, ['subject' => "Range {$days} before", 'created_at' => $start->copy()->subSecond()]);
            $allTickets[] = $this->ticket($requester, ['subject' => "Range {$days} end", 'created_at' => $today->copy()->endOfDay()]);
            $allTickets[] = $this->ticket($requester, ['subject' => "Range {$days} after", 'created_at' => $today->copy()->addDay()->startOfDay()]);

            $response = $this->actingAs($it)->get(route('tickets.export', [
                'type' => 'tickets',
                'range' => $days,
            ]))->assertOk();

            $this->assertStringContainsString("rekap-tiket-{$days}-hari-2026-10-16.csv", (string) $response->headers->get('content-disposition'));
            $rows = $this->csvRows($response->streamedContent());
            $exportedIds = array_map(fn (string $id) => (int) ltrim($id, '#'), array_column(array_slice($rows, 1), 0));
            $expectedIds = array_map(
                fn (Ticket $ticket) => $ticket->id,
                array_filter($allTickets, fn (Ticket $ticket) => $ticket->created_at->gte($start) && $ticket->created_at->lt($today->copy()->addDay())),
            );
            sort($exportedIds);
            sort($expectedIds);

            $this->assertSame($expectedIds, $exportedIds);
        }
    }

    public function test_export_period_uses_application_timezone_instead_of_php_default_timezone(): void
    {
        $it = $this->account('it');
        $requester = $this->account('employee');
        Carbon::setTestNow(Carbon::parse('2026-10-16 00:15:00', 'Asia/Jakarta'));
        date_default_timezone_set('UTC');
        $ticket = $this->ticket($requester, [
            'created_at' => Carbon::parse('2026-10-10 00:00:00', 'Asia/Jakarta'),
        ]);

        $ticketRows = $this->csvRows($this->actingAs($it)
            ->get(route('tickets.export', ['range' => 7]))
            ->assertOk()
            ->streamedContent());
        $statisticsRows = $this->csvRows($this->get(route('tickets.export', [
            'type' => 'statistics',
            'range' => 7,
        ]))->assertOk()->streamedContent());

        $this->assertSame(['2026-10-10', '2026-10-16'], [$statisticsRows[1][2], $statisticsRows[1][3]]);
        $this->assertSame('1', $this->metricValue($statisticsRows, 'Total tiket dibuat'));
        $this->assertSame(['#'.$ticket->id], array_column(array_slice($ticketRows, 1), 0));
    }

    public function test_statistics_export_accepts_each_supported_analytics_range(): void
    {
        $it = $this->account('it');
        $requester = $this->account('employee');
        $this->ticket($requester, [
            'created_at' => Carbon::parse('2026-10-16 09:00:00', 'Asia/Jakarta'),
        ]);

        foreach ([7, 30, 90] as $days) {
            $response = $this->actingAs($it)->get(route('tickets.export', [
                'type' => 'statistics',
                'range' => $days,
                'view' => 'analytics',
            ]))->assertOk();

            $rows = $this->csvRows($response->streamedContent());
            $expectedStart = Carbon::parse('2026-10-16', 'Asia/Jakarta')
                ->subDays($days - 1)
                ->toDateString();

            $this->assertSame([$expectedStart, '2026-10-16'], [$rows[1][2], $rows[1][3]]);
            $this->assertSame('1', $this->metricValue($rows, 'Total tiket dibuat'));
        }
    }

    public function test_statistics_export_rejects_ticket_filters_without_echoing_their_values(): void
    {
        $it = $this->account('it');
        $unsupportedFilters = [
            'status' => 'closed',
            'priority' => 'high',
            'category' => 'Perangkat',
            'q' => 'sensitive-ticket-query-marker',
            'date_from' => '2026-10-10',
            'date_to' => '2026-10-16',
            'mine' => '1',
        ];

        foreach ($unsupportedFilters as $filter => $value) {
            $response = $this->actingAs($it)->getJson(route('tickets.export', [
                'type' => 'statistics',
                'range' => 7,
                'view' => 'analytics',
                $filter => $value,
            ]))->assertUnprocessable()->assertJsonValidationErrors($filter);

            $this->assertStringContainsString('Filter tiket tidak didukung', $response->getContent());
            $this->assertStringNotContainsString('sensitive-ticket-query-marker', $response->getContent());
        }

        $this->actingAs($it)->getJson(route('tickets.export', [
            'type' => 'statistics',
            'range' => 7,
            'view' => 'analytics',
            'q' => '',
        ]))->assertUnprocessable()->assertJsonValidationErrors('q');
    }

    public function test_ticket_export_still_accepts_ticket_filters(): void
    {
        $it = $this->account('it');
        $requester = $this->account('employee');
        $matching = $this->ticket($requester, [
            'subject' => 'Match the requested filter',
            'status' => 'closed',
            'priority' => 'high',
            'category' => 'Perangkat',
            'assignee_id' => $it->id,
            'created_at' => Carbon::parse('2026-10-16 09:00:00', 'Asia/Jakarta'),
        ]);
        $this->ticket($requester, [
            'subject' => 'Does not match the status',
            'status' => 'new',
            'priority' => 'high',
            'category' => 'Perangkat',
        ]);

        $response = $this->actingAs($it)->get(route('tickets.export', [
            'type' => 'tickets',
            'range' => 7,
            'status' => 'closed',
            'priority' => 'high',
            'category' => 'Perangkat',
            'q' => 'Match the requested filter',
            'date_from' => '2026-10-16',
            'date_to' => '2026-10-16',
            'mine' => 1,
        ]))->assertOk();

        $rows = $this->csvRows($response->streamedContent());
        $this->assertCount(2, $rows);
        $this->assertSame('#'.$matching->id, $rows[1][0]);
    }

    public function test_empty_ticket_export_returns_a_valid_csv_header(): void
    {
        $it = $this->account('it');

        $response = $this->actingAs($it)->get(route('tickets.export', ['range' => 7]))->assertOk();
        $rows = $this->csvRows($response->streamedContent());

        $this->assertSame([[
            'ID Ticket', 'Subjek', 'Kategori', 'Prioritas', 'Status', 'Pemohon',
            'Departemen', 'Ditangani Oleh', 'Tanggal Dibuat', 'Tanggal Update', 'Catatan Resolusi',
        ]], $rows);
    }

    public function test_ticket_export_preserves_nullable_fields_and_csv_quoted_text(): void
    {
        $it = $this->account('it');
        $requester = $this->account('employee');
        $subject = "Router, \"lantai 2\"\nperlu restart";
        $resolutionNote = "Pengecekan, \"port 4\"\nselesai";
        $this->ticket($requester, [
            'subject' => $subject,
            'resolution_note' => $resolutionNote,
            'assignee_id' => null,
            'created_at' => Carbon::parse('2026-10-16 09:00:00', 'Asia/Jakarta'),
        ]);

        $response = $this->actingAs($it)->get(route('tickets.export', ['range' => 7]))->assertOk();
        $rows = $this->csvRows($response->streamedContent());

        $this->assertCount(2, $rows);
        $this->assertSame($subject, $rows[1][1]);
        $this->assertSame('-', $rows[1][7]);
        $this->assertSame($resolutionNote, $rows[1][10]);
    }

    public function test_csv_ticket_timestamps_use_the_application_timezone(): void
    {
        $it = $this->account('it');
        $requester = $this->account('employee');
        $this->ticket($requester, [
            'created_at' => Carbon::parse('2026-10-16 09:03:00', 'Asia/Jakarta'),
            'updated_at' => Carbon::parse('2026-10-16 14:35:00', 'Asia/Jakarta'),
        ]);

        $originalTimezone = date_default_timezone_get();

        try {
            date_default_timezone_set('UTC');
            $response = $this->actingAs($it)->get(route('tickets.export', ['range' => 7]))->assertOk();
            $rows = $this->csvRows($response->streamedContent());
        } finally {
            date_default_timezone_set($originalTimezone);
        }

        $this->assertSame('16/10/2026 09:03', $rows[1][8]);
        $this->assertSame('16/10/2026 14:35', $rows[1][9]);
    }

    public function test_ticket_export_applies_existing_filters_date_bounds_and_mine_without_page_limits(): void
    {
        $it = $this->account('it');
        $requester = $this->account('employee');
        $otherIt = $this->account('it');
        $matching = [];

        for ($index = 1; $index <= 25; $index++) {
            $matching[] = $this->ticket($requester, [
                'subject' => "Access request {$index}",
                'category' => 'Software',
                'priority' => 'high',
                'status' => 'in_progress',
                'assignee_id' => $it->id,
                'created_at' => Carbon::parse('2026-10-16 23:59:59', 'Asia/Jakarta'),
            ]);
        }

        $this->ticket($requester, [
            'subject' => 'Access request assigned elsewhere',
            'category' => 'Software',
            'priority' => 'high',
            'status' => 'in_progress',
            'assignee_id' => $otherIt->id,
            'created_at' => Carbon::parse('2026-10-16 12:00:00', 'Asia/Jakarta'),
        ]);
        $this->ticket($requester, [
            'subject' => 'Access request wrong status',
            'category' => 'Software',
            'priority' => 'high',
            'status' => 'new',
            'assignee_id' => $it->id,
            'created_at' => Carbon::parse('2026-10-16 12:00:00', 'Asia/Jakarta'),
        ]);
        $this->ticket($requester, [
            'subject' => 'Access request before selected dates',
            'category' => 'Software',
            'priority' => 'high',
            'status' => 'in_progress',
            'assignee_id' => $it->id,
            'created_at' => Carbon::parse('2026-10-09 23:59:59', 'Asia/Jakarta'),
        ]);

        $response = $this->actingAs($it)->get(route('tickets.export', [
            'range' => 7,
            'q' => 'Access request',
            'status' => 'in_progress',
            'priority' => 'high',
            'category' => 'Software',
            'date_from' => '2026-10-10',
            'date_to' => '2026-10-16',
            'mine' => 1,
        ]))->assertOk();

        $rows = $this->csvRows($response->streamedContent());
        $this->assertCount(26, $rows);
        $exportedIds = array_map(fn (string $id) => (int) ltrim($id, '#'), array_column(array_slice($rows, 1), 0));
        $matchingIds = array_map(fn (Ticket $ticket) => $ticket->id, $matching);
        sort($exportedIds);
        sort($matchingIds);
        $this->assertSame($matchingIds, $exportedIds);
    }

    public function test_statistics_csv_contains_only_period_scoped_creation_and_current_snapshot_metrics(): void
    {
        $it = $this->account('it');
        $requester = $this->account('employee');
        $this->ticket($requester, [
            'subject' => 'Recent new',
            'status' => 'new',
            'priority' => 'high',
            'created_at' => Carbon::parse('2026-10-16 09:00:00', 'Asia/Jakarta'),
        ]);
        $this->ticket($requester, [
            'subject' => 'Recent closed',
            'status' => 'closed',
            'priority' => 'low',
            'created_at' => Carbon::parse('2026-10-10 00:00:00', 'Asia/Jakarta'),
        ]);
        $this->ticket($requester, [
            'subject' => 'Older ticket',
            'status' => 'in_progress',
            'priority' => 'urgent',
            'created_at' => Carbon::parse('2026-10-09 23:59:59', 'Asia/Jakarta'),
        ]);

        $response = $this->actingAs($it)->get(route('tickets.export', [
            'type' => 'statistics',
            'range' => 7,
            'view' => 'analytics',
        ]))->assertOk();

        $rows = $this->csvRows($response->streamedContent());
        $this->assertSame(['Metrik', 'Nilai', 'Mulai Periode', 'Akhir Periode', 'Keterangan'], $rows[0]);
        $this->assertSame(['2026-10-10', '2026-10-16'], [$rows[1][2], $rows[1][3]]);
        $this->assertSame('2', $this->metricValue($rows, 'Total tiket dibuat'));
        $this->assertSame('1', $this->metricValue($rows, 'Status saat ekspor: Baru'));
        $this->assertSame('1', $this->metricValue($rows, 'Status saat ekspor: Ditutup'));
        $this->assertSame('1', $this->metricValue($rows, 'Prioritas saat ekspor: Tinggi'));
        $this->assertSame('1', $this->metricValue($rows, 'Tiket dibuat 2026-10-10'));
        $this->assertSame('1', $this->metricValue($rows, 'Tiket dibuat 2026-10-16'));

        $labels = array_column(array_slice($rows, 1), 0);
        foreach ($labels as $label) {
            $this->assertStringNotContainsString('SLA', $label);
            $this->assertStringNotContainsString('resolusi', mb_strtolower($label));
            $this->assertStringNotContainsString('kepatuhan', mb_strtolower($label));
        }
    }

    public function test_empty_statistics_export_has_zero_values_for_the_selected_period(): void
    {
        $it = $this->account('it');

        $response = $this->actingAs($it)->get(route('tickets.export', [
            'type' => 'statistics',
            'range' => 7,
        ]))->assertOk();

        $rows = $this->csvRows($response->streamedContent());
        $this->assertSame('0', $this->metricValue($rows, 'Total tiket dibuat'));
        $this->assertSame('0', $this->metricValue($rows, 'Tiket dibuat 2026-10-16'));
        $this->assertSame('0', $this->metricValue($rows, 'Status saat ekspor: Baru'));
        $this->assertSame('0', $this->metricValue($rows, 'Prioritas saat ekspor: Rendah'));
    }

    public function test_ticket_export_neutralizes_formula_prefixes_in_user_controlled_text(): void
    {
        $it = $this->account('it');
        $requester = $this->account('employee', [
            'name' => ' =HYPERLINK("https://example.test")',
            'department' => "\r@SUM(1,1)",
        ]);
        $payloads = [
            '=1+1',
            '+SUM(1,1)',
            '-1+1',
            '@SUM(1,1)',
            "\t=1+1",
            "\u{00A0}+1",
        ];
        $tickets = [];
        foreach ($payloads as $index => $payload) {
            $tickets[] = $this->ticket($requester, [
                'subject' => $payload,
                'category' => $index === 0 ? "\t+SUM(1,1)" : 'Software',
                'resolution_note' => $index === 0 ? "\n@SUM(1,1)" : '',
                'created_at' => Carbon::parse('2026-10-16 09:00:00', 'Asia/Jakarta'),
            ]);
        }

        $response = $this->actingAs($it)->get(route('tickets.export', ['range' => 7]))->assertOk();
        $rows = $this->csvRows($response->streamedContent());

        foreach (array_slice($rows, 1) as $index => $row) {
            $this->assertSame("'".$payloads[$index], $row[1]);
        }
        $this->assertSame("'\t+SUM(1,1)", $rows[1][2]);
        $this->assertSame("' =HYPERLINK(\"https://example.test\")", $rows[1][5]);
        $this->assertSame("'\r@SUM(1,1)", $rows[1][6]);
        $this->assertSame("'\n@SUM(1,1)", $rows[1][10]);
        $this->assertSame('#'.$tickets[0]->id, $rows[1][0]);
    }

    public function test_invalid_export_parameters_are_rejected_safely(): void
    {
        $it = $this->account('it');

        $this->actingAs($it)
            ->getJson(route('tickets.export', ['range' => ['7'], 'type' => 'statistics']))
            ->assertUnprocessable();
        $this->getJson(route('tickets.export', ['range' => 14, 'type' => 'statistics']))
            ->assertUnprocessable();
        $this->getJson(route('tickets.export', ['date_from' => '2026-10-17', 'date_to' => '2026-10-16']))
            ->assertUnprocessable();
    }

    public function test_analytics_page_links_both_exports_to_the_selected_range(): void
    {
        $it = $this->account('it');

        $response = $this->actingAs($it)
            ->get(route('dashboard', ['view' => 'analytics', 'range' => 7]))
            ->assertOk();

        $response->assertSee('Unduh rekap tiket');
        $response->assertSee('Unduh statistik');
        $response->assertSee(route('tickets.export', ['type' => 'tickets', 'range' => 7, 'view' => 'analytics']));
        $response->assertSee(route('tickets.export', ['type' => 'statistics', 'range' => 7, 'view' => 'analytics']));
    }

    public function test_ticket_list_export_uses_download_route_and_preserves_active_filters(): void
    {
        $it = $this->account('it');
        $requester = $this->account('employee');
        $this->ticket($requester, [
            'subject' => 'QA printer export target',
            'assignee_id' => $it->id,
        ]);
        $this->ticket($requester, [
            'subject' => 'QA monitor export unrelated',
            'description' => 'The display is unavailable.',
            'assignee_id' => $it->id,
        ]);

        $filters = [
            'type' => 'tickets',
            'q' => 'printer',
            'status' => 'new',
            'priority' => 'medium',
            'category' => 'Perangkat',
            'mine' => '1',
        ];
        $expectedUrl = route('tickets.export', $filters);
        $response = $this->actingAs($it)
            ->get(route('dashboard', [
                'view' => 'tickets',
                ...$filters,
            ]))
            ->assertOk();

        $this->assertSame(
            1,
            preg_match('/<a class="btn light compact" href="([^"]+)" title="Unduh daftar ticket dalam CSV">/', $response->getContent(), $matches),
        );
        $downloadUrl = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5);
        $this->assertSame($expectedUrl, $downloadUrl);
        $this->assertStringNotContainsString('index.php?view=tickets&amp;export=csv', $response->getContent());

        $download = $this->get($downloadUrl)
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment;', (string) $download->headers->get('content-disposition'));

        $rows = $this->csvRows($download->streamedContent());
        $subjects = array_column(array_slice($rows, 1), 1);
        $this->assertSame(['QA printer export target'], $subjects);
    }

    public function test_ticket_search_form_submits_query_and_keeps_active_filters(): void
    {
        $it = $this->account('it');
        $requester = $this->account('employee');
        $this->ticket($requester, [
            'subject' => 'QA printer search target',
            'assignee_id' => $it->id,
        ]);
        $this->ticket($requester, [
            'subject' => 'QA monitor search unrelated',
            'description' => 'The display is unavailable.',
            'assignee_id' => $it->id,
        ]);

        $response = $this->actingAs($it)
            ->get(route('dashboard', [
                'view' => 'tickets',
                'mine' => '1',
                'q' => 'printer',
                'status' => 'new',
                'priority' => 'medium',
                'category' => 'Perangkat',
            ]))
            ->assertOk();

        $response->assertSee('name="q"', false);
        $response->assertSee('name="mine" value="1"', false);
        $response->assertSee('class="btn primary compact filter-submit" type="submit">Cari</button>', false);
        $response->assertSee('value="printer"', false);
        $response->assertSee('QA printer search target');
        $response->assertDontSee('QA monitor search unrelated');
    }

    public function test_downloads_do_not_mutate_tickets_or_ticket_events(): void
    {
        $it = $this->account('it');
        $requester = $this->account('employee');
        $ticket = $this->ticket($requester, ['created_at' => Carbon::parse('2026-10-16 09:00:00')]);
        $before = $this->ticketSnapshot($ticket);
        $eventCount = TicketEvent::query()->count();

        $this->actingAs($it)->get(route('tickets.export', ['range' => 7]))->assertOk();
        $this->get(route('tickets.export', ['type' => 'statistics', 'range' => 7]))->assertOk();

        $this->assertSame($before, $this->ticketSnapshot($ticket));
        $this->assertSame($eventCount, TicketEvent::query()->count());
    }

    private function metricValue(array $rows, string $label): ?string
    {
        foreach (array_slice($rows, 1) as $row) {
            if (($row[0] ?? null) === $label) {
                return $row[1] ?? null;
            }
        }

        return null;
    }

    private function ticketSnapshot(Ticket $ticket): array
    {
        $ticket->refresh();

        return [
            'status' => $ticket->status,
            'assignee_id' => $ticket->assignee_id,
            'created_at' => $ticket->created_at?->toDateTimeString(),
            'updated_at' => $ticket->updated_at?->toDateTimeString(),
            'resolution_note' => $ticket->resolution_note,
        ];
    }

    private function csvRows(string $csv): array
    {
        $csv = preg_replace('/\A\xEF\xBB\xBF/', '', $csv) ?? $csv;
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $csv);
        rewind($stream);

        $rows = [];
        while (($row = fgetcsv($stream)) !== false) {
            $rows[] = $row;
        }
        fclose($stream);

        return $rows;
    }

    private function account(string $role, array $attributes = []): User
    {
        return User::query()->create(array_merge([
            'name' => 'Test '.ucfirst($role),
            'email' => $role.'-'.Str::uuid().'@example.test',
            'password' => Hash::make('password'),
            'role' => $role,
            'department' => 'Operations',
            'job_title' => 'Staff',
            'active' => true,
        ], $attributes));
    }

    private function ticket(User $requester, array $attributes = []): Ticket
    {
        return Ticket::query()->create(array_merge([
            'subject' => 'Printer cannot connect',
            'description' => 'The shared printer is unavailable.',
            'category' => 'Perangkat',
            'priority' => 'medium',
            'status' => 'new',
            'requester_id' => $requester->id,
            'assignee_id' => null,
            'resolution_note' => '',
            'created_at' => Carbon::parse('2026-10-16 09:00:00', 'Asia/Jakarta'),
            'updated_at' => Carbon::parse('2026-10-16 09:00:00', 'Asia/Jakarta'),
        ], $attributes));
    }
}
