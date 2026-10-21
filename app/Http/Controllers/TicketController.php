<?php

namespace App\Http\Controllers;

use App\Http\Requests\CommentRequest;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Models\Attachment;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TicketController extends Controller
{
    public function __construct(private readonly TicketService $tickets) {}

    public function create()
    {
        return redirect()->route('dashboard', ['view' => 'new']);
    }

    public function store(StoreTicketRequest $request)
    {
        try {
            $files = $request->file('screenshots', []);
            $files = is_array($files) ? $files : [$files];
            $ticket = $this->tickets->create($request->user(), $request->validated(), $files);
        } catch (\RuntimeException $error) {
            return back()->withInput()->with('error', $error->getMessage());
        }

        return redirect()->route('tickets.show', $ticket)->with('success', 'Ticket #'.$ticket->id.' berhasil dibuat.');
    }

    public function show(Ticket $ticket)
    {
        $this->authorizeView($ticket);

        return redirect()->route('dashboard', ['ticket' => $ticket->id]);
    }

    public function attachment(Request $request, Ticket $ticket, Attachment $attachment)
    {
        abort_unless((int) $attachment->ticket_id === (int) $ticket->id, 404);
        $this->authorizeView($ticket);

        $diskName = $attachment->disk ?: 'public';
        abort_unless(in_array($diskName, ['public', 'ticket_attachments'], true), 404);
        $storedName = basename($attachment->stored_name);
        abort_unless(
            $storedName === $attachment->stored_name
                && preg_match('/\A[A-Za-z0-9]{32}\.(?:jpg|png|webp|heic|heif)\z/', $storedName) === 1,
            404,
        );

        $path = 'uploads/'.$storedName;
        $disk = Storage::disk($diskName);
        abort_unless($disk->exists($path), 404);

        $mime = in_array($attachment->mime, [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/heic',
            'image/heif',
            'image/heic-sequence',
            'image/heif-sequence',
        ], true) ? $attachment->mime : 'application/octet-stream';
        $filename = preg_replace('/[\x00-\x1F\x7F"\\\\]/u', '_', basename($attachment->original_name)) ?: 'attachment';
        $inline = $request->boolean('preview') && in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true);

        return $disk->response($path, $filename, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ], $inline ? 'inline' : 'attachment');
    }

    public function comment(CommentRequest $request, Ticket $ticket)
    {
        $this->authorizeView($ticket);
        abort_if(in_array($ticket->status, ['closed', 'cancelled'], true), 403, 'Ticket final bersifat read-only dan tidak menerima komentar baru.');

        $this->tickets->addComment($ticket, $request->user(), $request->string('message')->toString());

        return redirect()->route('tickets.show', $ticket)->with('success', 'Komentar terkirim.');
    }

    public function claim(Request $request, Ticket $ticket)
    {
        $this->authorizeView($ticket);
        abort_unless($request->user()->role === 'it', 403, 'Ticket ini tidak dapat diambil.');
        abort_unless(! $ticket->assignee_id, 403, 'Ticket ini tidak dapat diambil.');

        $allowedTransitions = config('helpdesk.status_transitions.'.$ticket->status, []);
        if (! in_array('assigned', $allowedTransitions, true)) {
            return back()->with('error', 'Status ticket ini tidak memungkinkan claim.');
        }

        if (! $this->tickets->claim($ticket, $request->user())) {
            return back()->with('error', 'Ticket berubah atau baru saja diambil teknisi lain. Muat ulang halaman.');
        }

        return redirect()->route('tickets.show', $ticket)->with('success', 'Ticket berhasil kamu ambil.');
    }

    public function update(UpdateTicketRequest $request, Ticket $ticket)
    {
        $user = $request->user();
        $status = $request->string('status')->toString();
        $assigneeId = $user->role === 'admin' ? $request->integer('assignee_id') ?: null : $ticket->assignee_id;

        if ($assigneeId && $status === 'new') {
            $status = 'assigned';
        }
        if (! $assigneeId && $status === 'assigned') {
            $status = 'new';
        }
        if (! array_key_exists($status, config('helpdesk.statuses'))) {
            return back()->with('error', 'Status tidak valid.');
        }
        if (! in_array($status, config('helpdesk.status_transitions.'.$ticket->status, [$ticket->status]), true)) {
            $labels = config('helpdesk.statuses');

            return back()->with('error', 'Perubahan status dari '.($labels[$ticket->status] ?? $ticket->status).' ke '.($labels[$status] ?? $status).' tidak diizinkan.');
        }

        $resolutionNote = trim((string) $request->input('resolution_note', $ticket->resolution_note ?? ''));
        if (in_array($status, ['completed', 'closed'], true) && mb_strlen($resolutionNote) < 5) {
            return back()->with('error', 'Catatan penyelesaian wajib diisi saat ticket diselesaikan.');
        }

        $this->tickets->update($ticket, $user, $status, $assigneeId, $resolutionNote);

        return redirect()->route('tickets.show', $ticket)->with('success', 'Ticket berhasil diperbarui.');
    }

    public function resolution(Request $request, Ticket $ticket)
    {
        $this->authorizeView($ticket);
        $decision = $request->string('decision')->toString();
        abort_unless($request->user()->id === $ticket->requester_id && $ticket->status === 'completed' && in_array($decision, ['close', 'reopen'], true), 403, 'Keputusan ini tidak diizinkan.');

        $this->tickets->resolveByRequester($ticket, $request->user(), $decision);

        return redirect()->route('tickets.show', $ticket)->with('success', $decision === 'close' ? 'Ticket sudah ditutup. Terima kasih atas konfirmasinya.' : 'Ticket dibuka kembali dan dikirim ke teknisi.');
    }

    private function authorizeView(Ticket $ticket): void
    {
        $user = request()->user();
        abort_unless($user->role === 'admin' || $user->role === 'it' || $ticket->requester_id === $user->id, 403, 'Akses ditolak.');
    }
}
