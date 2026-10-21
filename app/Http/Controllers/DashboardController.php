<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Comment;
use App\Models\Ticket;
use App\Services\DashboardViewData;
use App\Services\TicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DashboardController extends Controller
{
    public function __construct(
        private readonly TicketService $tickets,
        private readonly DashboardViewData $dashboardData,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $view = $request->string('view', 'dashboard')->toString();

        if ($view === 'users' && $user->role !== 'admin') {
            abort(403, 'Akses khusus Admin.');
        }
        if ($view === 'analytics' && ! in_array($user->role, ['it', 'admin'], true)) {
            abort(403, 'Akses khusus tim IT dan Admin.');
        }

        if ($ticketId = $request->integer('ticket')) {
            $ticket = Ticket::find($ticketId);
            if ($ticket && ($user->role === 'admin' || $user->role === 'it' || $ticket->requester_id === $user->id)) {
                $this->tickets->markAsRead($ticket, $user);
            }
        }

        return view('dashboard', $this->dashboardData->build($request));
    }

    public function markAllNotificationsRead(Request $request)
    {
        $this->tickets->markAllAsRead($request->user());

        return redirect()->back()->with('success', 'Semua notifikasi sudah ditandai dibaca.');
    }

    public function updates(Request $request)
    {
        $user = $request->user();
        $since = $request->input('since') ? strtotime($request->input('since')) : time();
        $sinceAt = Carbon::createFromTimestamp($since ?: time());

        $visibleIds = Ticket::query()
            ->when($user->role === 'employee', fn ($query) => $query->where('requester_id', $user->id))
            ->pluck('id');

        $events = Comment::query()
            ->whereIn('ticket_id', $visibleIds)
            ->where('user_id', '!=', $user->id)
            ->where('created_at', '>', $sinceAt)
            ->get(['ticket_id', 'created_at'])
            ->map(fn (Comment $comment) => [
                'ticket_id' => (int) $comment->ticket_id,
                'type' => 'comment',
                'message' => 'Komentar baru',
                'created_at' => $comment->created_at->toIso8601String(),
            ]);

        $activityEvents = Activity::query()
            ->whereIn('ticket_id', $visibleIds)
            ->where('user_id', '!=', $user->id)
            ->where('message', '!=', 'Menambahkan komentar')
            ->where('created_at', '>', $sinceAt)
            ->get(['ticket_id', 'message', 'created_at'])
            ->map(fn (Activity $activity) => [
                'ticket_id' => (int) $activity->ticket_id,
                'type' => 'activity',
                'message' => $activity->message,
                'created_at' => $activity->created_at->toIso8601String(),
            ]);

        $events = $events->concat($activityEvents)->sortBy('created_at')->values();

        return response()->json([
            'changed' => $events->isNotEmpty(),
            'events' => $events,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function export(Request $request)
    {
        $user = $request->user();
        abort_unless(in_array($user->role, ['it', 'admin'], true), 403, 'Akses ditolak.');

        $validated = $request->validate([
            'type' => ['sometimes', 'string', 'in:tickets,statistics'],
            'range' => ['required_if:type,statistics', 'in:7,30,90'],
        ]);

        $type = $validated['type'] ?? 'tickets';

        if ($type === 'statistics') {
            foreach (['status', 'priority', 'category', 'q', 'date_from', 'date_to', 'mine'] as $unsupportedFilter) {
                if ($request->query->has($unsupportedFilter)) {
                    throw ValidationException::withMessages([
                        $unsupportedFilter => 'Filter tiket tidak didukung untuk ekspor statistik.',
                    ]);
                }
            }

            $validated = array_merge($validated, $request->validate([
                'view' => ['sometimes', 'in:analytics'],
            ]));
        } else {
            $validated = array_merge($validated, $request->validate([
                'status' => ['nullable', 'string', Rule::in(array_keys(config('helpdesk.statuses', [])))],
                'priority' => ['nullable', 'string', Rule::in(array_keys(config('helpdesk.priorities', [])))],
                'category' => ['nullable', 'string', Rule::in(config('helpdesk.categories', []))],
                'q' => ['nullable', 'string', 'max:255'],
                'date_from' => ['nullable', 'date_format:Y-m-d'],
                'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
                'mine' => ['sometimes', 'in:0,1'],
            ]));
        }

        $rangeDays = isset($validated['range']) ? (int) $validated['range'] : null;

        if ($type === 'statistics') {
            $request->query->set('view', 'analytics');
            $data = $this->dashboardData->build($request);
            $summary = $data['analytics']['period_summary'];
            $rangeDays = $data['analytics']['range_days'];
            $filename = 'statistik-analitik-'.$rangeDays.'-hari-'.now(config('app.timezone'))->format('Y-m-d').'.csv';

            return response()->streamDownload(function () use ($summary): void {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, ['Metrik', 'Nilai', 'Mulai Periode', 'Akhir Periode', 'Keterangan']);

                $period = [$summary['start_date'], $summary['end_date']];
                fputcsv($out, [
                    $this->csvSafeText('Total tiket dibuat'),
                    $summary['total_created'],
                    ...$period,
                    $this->csvSafeText('Berdasarkan created_at; tanggal lokal '.$summary['timezone']),
                ]);

                foreach ($summary['daily_created'] as $date => $amount) {
                    fputcsv($out, [
                        $this->csvSafeText('Tiket dibuat '.$date),
                        $amount,
                        ...$period,
                        $this->csvSafeText('Jumlah tiket dengan created_at pada tanggal tersebut.'),
                    ]);
                }

                foreach ($summary['status_snapshot'] as $status => $amount) {
                    $label = config('helpdesk.statuses.'.$status, $status);
                    fputcsv($out, [
                        $this->csvSafeText('Status saat ekspor: '.$label),
                        $amount,
                        ...$period,
                        $this->csvSafeText('Snapshot status sekarang untuk tiket yang dibuat dalam periode.'),
                    ]);
                }

                foreach ($summary['priority_snapshot'] as $priority => $amount) {
                    $label = config('helpdesk.priorities.'.$priority, $priority);
                    fputcsv($out, [
                        $this->csvSafeText('Prioritas saat ekspor: '.$label),
                        $amount,
                        ...$period,
                        $this->csvSafeText('Snapshot prioritas sekarang untuk tiket yang dibuat dalam periode.'),
                    ]);
                }

                fclose($out);
            }, $filename, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Cache-Control' => 'private, no-store, max-age=0',
            ]);
        }

        $timezone = config('app.timezone', 'UTC');
        [$rangeStart, $rangeEndExclusive] = $rangeDays
            ? $this->dashboardData->analyticsPeriodBounds($rangeDays)
            : [null, null];
        $dateFrom = isset($validated['date_from'])
            ? Carbon::parse($validated['date_from'], $timezone)->startOfDay()
            : null;
        $dateToExclusive = isset($validated['date_to'])
            ? Carbon::parse($validated['date_to'], $timezone)->startOfDay()->addDay()
            : null;
        $lowerBound = $rangeStart && $dateFrom ? ($rangeStart->gt($dateFrom) ? $rangeStart : $dateFrom) : ($rangeStart ?? $dateFrom);
        $upperBound = $rangeEndExclusive && $dateToExclusive
            ? ($rangeEndExclusive->lt($dateToExclusive) ? $rangeEndExclusive : $dateToExclusive)
            : ($rangeEndExclusive ?? $dateToExclusive);

        $tickets = Ticket::query()->with(['requester', 'assignee'])->latest('updated_at')->get();
        $tickets = $tickets->filter(function (Ticket $ticket) use ($validated, $lowerBound, $upperBound): bool {
            if (! empty($validated['status']) && $ticket->status !== $validated['status']) {
                return false;
            }
            if (! empty($validated['priority']) && $ticket->priority !== $validated['priority']) {
                return false;
            }
            if (! empty($validated['category']) && $ticket->category !== $validated['category']) {
                return false;
            }
            if (! empty($validated['q'])) {
                $query = mb_strtolower(trim($validated['q']));
                $haystack = mb_strtolower($ticket->subject.' '.$ticket->id.' '.$ticket->description.' '.($ticket->requester?->name ?? ''));
                if (! str_contains($haystack, $query)) {
                    return false;
                }
            }
            if (! empty($validated['mine']) && $ticket->assignee_id !== auth()->id()) {
                return false;
            }
            if ($lowerBound && $ticket->created_at->lt($lowerBound)) {
                return false;
            }
            if ($upperBound && $ticket->created_at->gte($upperBound)) {
                return false;
            }

            return true;
        });

        $filename = $rangeDays
            ? 'rekap-tiket-'.$rangeDays.'-hari-'.now($timezone)->format('Y-m-d').'.csv'
            : 'tickets-'.now($timezone)->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($tickets, $timezone): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['ID Ticket', 'Subjek', 'Kategori', 'Prioritas', 'Status', 'Pemohon', 'Departemen', 'Ditangani Oleh', 'Tanggal Dibuat', 'Tanggal Update', 'Catatan Resolusi']);
            foreach ($tickets as $ticket) {
                fputcsv($out, [
                    '#'.$ticket->id,
                    $this->csvSafeText($ticket->subject),
                    $this->csvSafeText($ticket->category),
                    $this->csvSafeText(config('helpdesk.priorities')[$ticket->priority] ?? $ticket->priority),
                    $this->csvSafeText(config('helpdesk.statuses')[$ticket->status] ?? $ticket->status),
                    $this->csvSafeText($ticket->requester?->name ?? '-'),
                    $this->csvSafeText($ticket->requester?->department ?? '-'),
                    $this->csvSafeText($ticket->assignee?->name ?? '-'),
                    $this->formatCsvTimestamp($ticket->getRawOriginal('created_at'), $timezone),
                    $this->formatCsvTimestamp($ticket->getRawOriginal('updated_at'), $timezone),
                    $this->csvSafeText($ticket->resolution_note ?? ''),
                ]);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    private function csvSafeText(?string $value): string
    {
        $value ??= '';
        if ($value === '-') {
            return $value;
        }

        $unicodeFormulaPrefix = preg_match('/\A[\p{Z}\p{Cc}\p{Cf}]*[=+\-@]/u', $value);
        $asciiFormulaPrefix = preg_match('/\A[\x00-\x20\x7F]*[=+\-@]/', $value);

        return $unicodeFormulaPrefix === 1 || $asciiFormulaPrefix === 1 ? "'".$value : $value;
    }

    private function formatCsvTimestamp(?string $value, string $timezone): string
    {
        return $value === null
            ? ''
            : Carbon::parse($value, $timezone)->format('d/m/Y H:i');
    }
}
