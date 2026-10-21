<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\Comment;
use App\Models\Ticket;
use App\Models\TicketRead;
use App\Models\User;
use App\Services\TicketEventRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class DemoTicketSeeder extends Seeder
{
    private const TICKET_COUNT = 96;

    private const SUBJECT_PREFIX = '[DEMO-IT-v1-';

    private const PROVENANCE_ACTIVITY = 'Data demo IT Support v1 untuk pengujian.';

    private const STATUS_PATHS = [
        'new' => [],
        'assigned' => ['assigned'],
        'in_progress' => ['assigned', 'in_progress'],
        'completed' => ['assigned', 'in_progress', 'completed'],
        'closed' => ['assigned', 'in_progress', 'completed', 'closed'],
        'cancelled' => ['assigned', 'cancelled'],
    ];

    private const TOPICS = [
        'Perangkat' => 'Laptop atau perangkat kerja',
        'Software' => 'Aplikasi kerja dan perangkat lunak',
        'Jaringan' => 'Koneksi jaringan kantor',
        'Akun & Akses' => 'Akun dan akses layanan internal',
        'Email' => 'Email dan kalender kerja',
        'Lainnya' => 'Permintaan bantuan IT lainnya',
    ];

    private ?bool $eventTableAvailable = null;

    public function run(): void
    {
        $this->assertRequiredTablesExist();

        $accounts = User::query()
            ->where('active', true)
            ->whereIn('role', array_keys(config('helpdesk.roles', [])))
            ->orderBy('id')
            ->get();
        $technicians = $accounts->where('role', 'it')->values();

        if ($accounts->isEmpty()) {
            throw new RuntimeException('Seeder demo membutuhkan setidaknya satu akun aktif yang sudah ada. Tidak ada akun yang dibuat atau diubah.');
        }

        if ($technicians->isEmpty()) {
            throw new RuntimeException('Seeder demo membutuhkan setidaknya satu teknisi aktif yang sudah ada. Tidak ada akun yang dibuat atau diubah.');
        }

        $this->assertSupportedConfiguration();

        $expectedSubjects = $this->expectedSubjects();
        $existing = Ticket::query()
            ->where('subject', 'like', self::SUBJECT_PREFIX.'%')
            ->with('activities')
            ->get();

        if ($existing->isNotEmpty()) {
            $expected = array_fill_keys($expectedSubjects, true);

            foreach ($existing as $ticket) {
                if (! isset($expected[$ticket->subject])) {
                    throw new RuntimeException('Prefix penanda demo dipakai record yang tidak dikenal. Seeder berhenti tanpa mengubah data.');
                }

                if (! $ticket->activities->contains('message', self::PROVENANCE_ACTIVITY)) {
                    throw new RuntimeException('Record dengan prefix demo tidak memiliki penanda asal seeder. Seeder berhenti tanpa mengubah data.');
                }
            }

            if ($existing->count() !== self::TICKET_COUNT
                || array_diff($expectedSubjects, $existing->pluck('subject')->all()) !== []) {
                throw new RuntimeException('Dataset demo terdeteksi tidak lengkap. Seeder tidak akan menebak atau mengubah record yang ada.');
            }

            $this->command?->info('Dataset demo sudah lengkap: 96 tiket dipertahankan tanpa perubahan.');

            return;
        }

        DB::transaction(function () use ($accounts, $technicians): void {
            $now = CarbonImmutable::now(config('app.timezone'));
            $statuses = array_keys(config('helpdesk.statuses', []));
            $priorities = array_keys(config('helpdesk.priorities', []));
            $categories = array_values(config('helpdesk.categories', []));

            for ($number = 1; $number <= self::TICKET_COUNT; $number++) {
                $index = $number - 1;
                $status = $statuses[$index % count($statuses)];
                $priority = $priorities[$index % count($priorities)];
                $category = $categories[$index % count($categories)];
                $requester = $accounts[$index % $accounts->count()];
                $assignee = $status === 'new'
                    ? null
                    : $technicians[$index % $technicians->count()];
                $createdAt = $this->createdAt($now, $index);
                $subject = $this->subject($number, $category);
                $resolutionNote = in_array($status, ['completed', 'closed'], true)
                    ? 'Akses atau perangkat sudah diperiksa dan hasilnya berhasil diverifikasi.'
                    : '';
                $slaHours = (int) (config('helpdesk.sla_hours.'.$priority) ?? 24);

                $ticket = Ticket::query()->create([
                    'subject' => $subject,
                    'description' => $this->description($index, $category),
                    'category' => $category,
                    'priority' => $priority,
                    'status' => 'new',
                    'requester_id' => $requester->id,
                    'assignee_id' => null,
                    'resolution_note' => '',
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                    'sla_deadline' => $createdAt->addHours($slaHours),
                ]);

                $lifecycle = $this->lifecycle($status, $assignee, $createdAt);
                $comments = $this->comments($requester, $assignee, $createdAt, $index);
                $updatedAt = collect($lifecycle)
                    ->pluck('at')
                    ->merge(collect($comments)->pluck('at'))
                    ->push($createdAt)
                    ->max();

                $ticket->forceFill([
                    'status' => $status,
                    'assignee_id' => $assignee?->id,
                    'resolution_note' => $resolutionNote,
                    'updated_at' => $updatedAt,
                ])->save();

                Activity::query()->create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $requester->id,
                    'message' => self::PROVENANCE_ACTIVITY,
                    'created_at' => $createdAt,
                ]);

                foreach ($lifecycle as $step) {
                    Activity::query()->create([
                        'ticket_id' => $ticket->id,
                        'user_id' => $step['actor']->id,
                        'message' => $step['message'],
                        'created_at' => $step['at'],
                    ]);
                }

                foreach ($comments as $entry) {
                    $comment = Comment::query()->create([
                        'ticket_id' => $ticket->id,
                        'user_id' => $entry['actor']->id,
                        'message' => $entry['message'],
                        'created_at' => $entry['at'],
                    ]);

                    Activity::query()->create([
                        'ticket_id' => $ticket->id,
                        'user_id' => $entry['actor']->id,
                        'message' => 'Menambahkan komentar',
                        'created_at' => $entry['at'],
                    ]);

                    $this->recordCommentEvent($ticket, $comment, $entry['actor'], $entry['at']);
                }

                $this->recordLifecycleEvents($ticket, $requester, $priority, $lifecycle, $createdAt);
                $this->seedReadMarkers($ticket, $requester, $assignee, $createdAt, $index);
            }
        });

        $this->command?->info('Dataset demo dibuat: 96 tiket. Akun pengguna tidak dibuat atau diubah.');
        if (! Schema::hasTable('ticket_events')) {
            $this->command?->warn('Tabel ticket_events belum tersedia; event SLA tidak dibuat dan histori lama tidak direkonstruksi.');
        }
    }

    private function assertRequiredTablesExist(): void
    {
        foreach (['users', 'tickets', 'comments', 'activities', 'ticket_reads'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("Tabel {$table} belum tersedia. Seeder berhenti tanpa menjalankan migration.");
            }
        }
    }

    private function assertSupportedConfiguration(): void
    {
        $statusLabels = config('helpdesk.statuses', []);
        $statuses = array_keys($statusLabels);
        $priorities = array_keys(config('helpdesk.priorities', []));
        $categories = array_values(config('helpdesk.categories', []));

        if (! isset($statusLabels['new'])
            || array_diff($statuses, array_keys(self::STATUS_PATHS)) !== []
            || array_diff(array_keys(self::STATUS_PATHS), $statuses) !== []) {
            throw new RuntimeException('Daftar status helpdesk tidak dapat dipetakan ke lifecycle demo yang aman.');
        }

        if ($priorities === [] || $categories === []) {
            throw new RuntimeException('Prioritas atau kategori helpdesk belum dikonfigurasi.');
        }

        foreach ($priorities as $priority) {
            $slaHours = config('helpdesk.sla_hours.'.$priority);
            if (! is_numeric($slaHours) || (float) $slaHours <= 0) {
                throw new RuntimeException("Target legacy sla_hours untuk prioritas {$priority} tidak tersedia.");
            }
        }

        foreach (self::STATUS_PATHS as $targetStatus => $path) {
            $currentStatus = 'new';
            foreach ($path as $nextStatus) {
                if (! in_array($nextStatus, config('helpdesk.status_transitions.'.$currentStatus, []), true)) {
                    throw new RuntimeException("Transisi {$currentStatus} ke {$nextStatus} tidak diizinkan oleh konfigurasi helpdesk.");
                }

                $currentStatus = $nextStatus;
            }

            if ($currentStatus !== $targetStatus) {
                throw new RuntimeException("Lifecycle demo untuk status {$targetStatus} tidak konsisten.");
            }
        }
    }

    private function expectedSubjects(): array
    {
        $categories = array_values(config('helpdesk.categories', []));

        return array_map(
            fn (int $number): string => $this->subject($number, $categories[($number - 1) % count($categories)]),
            range(1, self::TICKET_COUNT),
        );
    }

    private function subject(int $number, string $category): string
    {
        $topic = self::TOPICS[$category] ?? ($category !== '' ? "Layanan {$category}" : 'layanan IT');

        if ($number === 1) {
            $topic = '"Printer, ruang rapat" gagal mencetak';
        } elseif ($number === 2) {
            $topic = "Akses folder, \"Proyek Inti\"\nperlu diperiksa";
        }

        return sprintf('%s%03d] %s', self::SUBJECT_PREFIX, $number, $topic);
    }

    private function createdAt(CarbonImmutable $now, int $index): CarbonImmutable
    {
        $daysAgo = $index % 91;
        $createdAt = $now->subDays($daysAgo)
            ->startOfDay()
            ->addHours(8 + ($index % 9))
            ->addMinutes(($index * 7) % 60);

        if ($createdAt->greaterThan($now)) {
            return $now->subMinute();
        }

        return $createdAt;
    }

    private function description(int $index, string $category): string
    {
        $topic = self::TOPICS[$category] ?? "Layanan {$category}";
        $templates = [
            "Saya membutuhkan bantuan untuk {$topic}. Masalah muncul saat pekerjaan harian dan masih terjadi setelah dicoba ulang.",
            'Kendala terlihat ketika membuka layanan. Pesan yang muncul: "akses belum tersedia, silakan coba lagi". Waktu kejadian tercatat oleh pengguna.',
            "Mohon diperiksa untuk {$topic}.\nLangkah yang sudah dicoba:\n1. Keluar lalu masuk kembali.\n2. Mengulang proses pada perangkat yang sama.",
            "Permintaan dari pengguna kantor, terkait {$topic}; dampaknya menghambat pekerjaan, tetapi belum menghentikan seluruh layanan.",
        ];

        return $templates[$index % count($templates)];
    }

    private function lifecycle(string $targetStatus, ?User $assignee, CarbonImmutable $createdAt): array
    {
        $steps = [];
        $currentStatus = 'new';
        $currentAssigneeId = null;

        foreach (self::STATUS_PATHS[$targetStatus] as $stepNumber => $nextStatus) {
            if ($assignee === null) {
                throw new RuntimeException("Status {$targetStatus} membutuhkan teknisi aktif yang sudah ada.");
            }

            $at = $createdAt->addMinutes([10, 30, 180, 240][$stepNumber] ?? (10 + ($stepNumber * 30)));
            $message = $nextStatus === 'assigned' && $currentStatus === 'new'
                ? 'Mengambil ticket dan menjadi teknisi yang ditugaskan'
                : 'Status diubah menjadi '.(config('helpdesk.statuses.'.$nextStatus) ?? $nextStatus);

            $steps[] = [
                'from_status' => $currentStatus,
                'to_status' => $nextStatus,
                'from_assignee_id' => $currentAssigneeId,
                'to_assignee_id' => $assignee->id,
                'actor' => $assignee,
                'at' => $at,
                'message' => $message,
            ];

            $currentStatus = $nextStatus;
            $currentAssigneeId = $assignee->id;
        }

        return $steps;
    }

    private function comments(User $requester, ?User $assignee, CarbonImmutable $createdAt, int $index): array
    {
        $messages = [
            'Saya sudah mencoba kembali, tetapi kendala masih muncul pada langkah yang sama.',
            "Saat login, saya melihat pesan \"akun, akses\" pada halaman layanan.\nSaya sertakan waktu kejadian agar mudah diperiksa.",
            'Kendala terjadi sesekali; aplikasi lain tetap dapat digunakan. Mohon kabari jika diperlukan informasi tambahan.',
            'Terima kasih, saya sudah menguji ulang hasil perbaikannya dan layanan dapat dipakai kembali.',
        ];
        $entries = [[
            'actor' => $requester,
            'at' => $createdAt->addMinutes(20),
            'message' => $messages[$index % count($messages)],
        ]];

        if ($assignee && $index % 3 !== 0) {
            $entries[] = [
                'actor' => $assignee,
                'at' => $createdAt->addMinutes(60),
                'message' => 'Sedang diperiksa. Saya sudah mengulang langkahnya dan akan mencatat hasil pengecekan di tiket ini.',
            ];
        }

        return $entries;
    }

    private function seedReadMarkers(Ticket $ticket, User $requester, ?User $assignee, CarbonImmutable $createdAt, int $index): void
    {
        if ($index % 2 === 0) {
            TicketRead::query()->create([
                'ticket_id' => $ticket->id,
                'user_id' => $requester->id,
                'last_read_at' => $createdAt->addMinutes(25),
            ]);
        }

        if ($assignee && $index % 3 === 0) {
            TicketRead::query()->create([
                'ticket_id' => $ticket->id,
                'user_id' => $assignee->id,
                'last_read_at' => $createdAt->addMinutes(45),
            ]);
        }
    }

    private function recordLifecycleEvents(
        Ticket $ticket,
        User $requester,
        string $priority,
        array $steps,
        CarbonImmutable $createdAt,
    ): void {
        if (! $this->hasEventTable()) {
            return;
        }

        $recorder = app(TicketEventRecorder::class);
        $recorder->record(
            $ticket,
            'ticket.created',
            'ticket.created',
            $requester,
            'ticket.create',
            $createdAt->utc(),
            [
                'command_id' => (string) Str::uuid(),
                'to_status' => 'new',
                'to_priority' => $priority,
            ],
        );

        foreach ($steps as $number => $step) {
            $eventType = 'ticket.status_changed';
            $eventKey = 'demo.seed.v1.state.'.($number + 1);
            $attributes = [
                'command_id' => (string) Str::uuid(),
                'from_status' => $step['from_status'],
                'to_status' => $step['to_status'],
                'from_assignee_id' => $step['from_assignee_id'],
                'to_assignee_id' => $step['to_assignee_id'],
            ];

            if ($step['to_status'] === 'completed') {
                $eventType = 'ticket.first_resolution';
                $eventKey = 'ticket.first_resolution';
                $attributes['resolution_cycle'] = 1;
            } elseif ($step['to_status'] === 'closed') {
                $eventType = 'ticket.closed';
                $eventKey = 'ticket.closed.1';
                $attributes['resolution_cycle'] = 1;
            } elseif ($step['to_status'] === 'cancelled') {
                $eventType = 'ticket.cancelled';
                $eventKey = 'ticket.cancelled';
            }

            $recorder->record(
                $ticket,
                $eventKey,
                $eventType,
                $step['actor'],
                'demo.seeder.v1.lifecycle',
                $step['at']->utc(),
                $attributes,
            );
        }
    }

    private function recordCommentEvent(Ticket $ticket, Comment $comment, User $actor, CarbonImmutable $occurredAt): void
    {
        if (! $this->hasEventTable()) {
            return;
        }

        app(TicketEventRecorder::class)->record(
            $ticket,
            'comment.'.$comment->id,
            'ticket.public_comment_added',
            $actor,
            'ticket.comment',
            $occurredAt->utc(),
            [
                'command_id' => (string) Str::uuid(),
                'comment_id' => $comment->id,
            ],
        );
    }

    private function hasEventTable(): bool
    {
        return $this->eventTableAvailable ??= Schema::hasTable('ticket_events');
    }
}
