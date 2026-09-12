<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Comment;
use App\Models\Ticket;
use App\Services\DashboardViewData;
use App\Services\TicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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

        $tickets = Ticket::query()->with(['requester', 'assignee'])->latest('updated_at')->get();
        $tickets = $tickets->filter(function (Ticket $ticket) use ($request): bool {
            if ($request->filled('status') && $ticket->status !== $request->input('status')) {
                return false;
            }
            if ($request->filled('priority') && $ticket->priority !== $request->input('priority')) {
                return false;
            }
            if ($request->filled('category') && $ticket->category !== $request->input('category')) {
                return false;
            }
            if ($request->filled('q')) {
                $query = mb_strtolower(trim($request->input('q')));
                $haystack = mb_strtolower($ticket->subject.' '.$ticket->id.' '.$ticket->description.' '.($ticket->requester?->name ?? ''));
                if (! str_contains($haystack, $query)) {
                    return false;
                }
            }
            if ($request->filled('date_from') && $ticket->created_at->lt(Carbon::parse($request->input('date_from'))->startOfDay())) {
                return false;
            }
            if ($request->filled('date_to') && $ticket->created_at->gt(Carbon::parse($request->input('date_to'))->endOfDay())) {
                return false;
            }

            return true;
        });

        return response()->streamDownload(function () use ($tickets): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['ID Ticket', 'Subjek', 'Kategori', 'Prioritas', 'Status', 'Pemohon', 'Departemen', 'Ditangani Oleh', 'Tanggal Dibuat', 'Tanggal Update', 'Catatan Resolusi']);
            foreach ($tickets as $ticket) {
                fputcsv($out, [
                    '#'.$ticket->id,
                    $ticket->subject,
                    $ticket->category,
                    config('helpdesk.priorities')[$ticket->priority] ?? $ticket->priority,
                    config('helpdesk.statuses')[$ticket->status] ?? $ticket->status,
                    $ticket->requester?->name ?? '-',
                    $ticket->requester?->department ?? '-',
                    $ticket->assignee?->name ?? '-',
                    $ticket->created_at->format('d/m/Y H:i'),
                    $ticket->updated_at->format('d/m/Y H:i'),
                    $ticket->resolution_note ?? '',
                ]);
            }
            fclose($out);
        }, 'tickets-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
