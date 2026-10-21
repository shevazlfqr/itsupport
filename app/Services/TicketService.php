<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Comment;
use App\Models\Ticket;
use App\Models\TicketRead;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TicketService
{
    public function __construct(private readonly TicketEventRecorder $eventRecorder) {}

    public function create(User $requester, array $attributes, array $files = []): Ticket
    {
        return DB::transaction(function () use ($requester, $attributes, $files): Ticket {
            $eventTime = now('UTC')->startOfSecond();
            $now = $eventTime->copy()->setTimezone(config('app.timezone'));
            $ticket = Ticket::create([
                'subject' => trim($attributes['subject']),
                'description' => trim($attributes['description']),
                'category' => $attributes['category'],
                'priority' => $attributes['priority'],
                'status' => 'new',
                'requester_id' => $requester->id,
                'assignee_id' => null,
                'resolution_note' => '',
                'created_at' => $now,
                'updated_at' => $now,
                'sla_deadline' => $this->slaDeadline($now, $attributes['priority']),
            ]);

            $this->eventRecorder->record(
                $ticket,
                'ticket.created',
                'ticket.created',
                $requester,
                'ticket.create',
                $eventTime,
                [
                    'to_status' => 'new',
                    'to_priority' => $attributes['priority'],
                ],
            );

            $storedFiles = $this->storeFiles($files);
            try {
                foreach ($storedFiles as $file) {
                    $ticket->attachments()->create([
                        'user_id' => $requester->id,
                        ...$file,
                        'created_at' => $now,
                    ]);
                }
            } catch (\Throwable $error) {
                foreach ($storedFiles as $file) {
                    Storage::disk($file['disk'])->delete('uploads/'.$file['stored_name']);
                }
                throw $error;
            }

            $this->activity($ticket, $requester, 'Ticket dibuat', $now);

            return $ticket->fresh();
        });
    }

    public function addComment(Ticket $ticket, User $user, string $message): Comment
    {
        return DB::transaction(function () use ($ticket, $user, $message): Comment {
            $eventTime = now('UTC')->startOfSecond();
            $now = $eventTime->copy()->setTimezone(config('app.timezone'));
            $comment = $ticket->comments()->create([
                'user_id' => $user->id,
                'message' => trim($message),
                'created_at' => $now,
            ]);

            $this->eventRecorder->record(
                $ticket,
                'comment.'.$comment->id,
                'ticket.public_comment_added',
                $user,
                'ticket.comment',
                $eventTime,
                ['comment_id' => $comment->id],
            );

            $ticket->update(['updated_at' => $now]);
            $this->markAsRead($ticket, $user, $now);
            $this->activity($ticket, $user, 'Menambahkan komentar', $now);

            return $comment;
        });
    }

    public function claim(Ticket $ticket, User $user): bool
    {
        if ($user->role !== 'it') {
            throw new AuthorizationException('Ticket ini tidak dapat diambil.');
        }

        $expectedStatus = $ticket->status;
        $allowedTransitions = config('helpdesk.status_transitions.'.$expectedStatus, []);
        if (! in_array('assigned', $allowedTransitions, true)) {
            return false;
        }

        return DB::transaction(function () use ($ticket, $user, $expectedStatus): bool {
            $eventTime = now('UTC')->startOfSecond();
            $now = $eventTime->copy()->setTimezone(config('app.timezone'));

            $updated = Ticket::query()
                ->whereKey($ticket->getKey())
                ->where('status', $expectedStatus)
                ->whereNull('assignee_id')
                ->update([
                    'assignee_id' => $user->id,
                    'status' => 'assigned',
                    'updated_at' => $now,
                ]);

            if ($updated !== 1) {
                return false;
            }

            $claimedTicket = $ticket->fresh();
            $commandId = (string) Str::uuid();
            $this->eventRecorder->record(
                $claimedTicket,
                'command:'.$commandId,
                'ticket.status_changed',
                $user,
                'ticket.claim',
                $eventTime,
                [
                    'command_id' => $commandId,
                    'from_status' => $expectedStatus,
                    'to_status' => 'assigned',
                    'from_assignee_id' => null,
                    'to_assignee_id' => $user->id,
                ],
            );
            $this->activity($claimedTicket, $user, 'Mengambil ticket dan menjadi teknisi yang ditugaskan', $now);

            return true;
        });
    }

    public function update(Ticket $ticket, User $actor, string $status, ?int $assigneeId, string $resolutionNote): void
    {
        DB::transaction(function () use ($ticket, $actor, $status, $assigneeId, $resolutionNote): void {
            $current = Ticket::query()->lockForUpdate()->findOrFail($ticket->getKey());
            $this->authorizeUpdate($current, $actor);

            if ($actor->role === 'it' && (int) $assigneeId !== (int) $current->assignee_id) {
                throw new AuthorizationException('Teknisi tidak dapat mengubah assignment ticket ini.');
            }
            if ($assigneeId !== null && ! User::query()
                ->whereKey($assigneeId)
                ->where('role', 'it')
                ->where('active', true)
                ->exists()) {
                throw ValidationException::withMessages([
                    'assignee_id' => 'Assignee harus merupakan teknisi aktif.',
                ]);
            }

            $allowedTransitions = config('helpdesk.status_transitions.'.$current->status, []);
            if (! array_key_exists($status, config('helpdesk.statuses', []))
                || ! in_array($status, $allowedTransitions, true)) {
                throw ValidationException::withMessages([
                    'status' => 'Perubahan status ticket tidak diizinkan.',
                ]);
            }

            if ($status === 'completed' && $current->status !== 'completed' && mb_strlen(trim($resolutionNote)) < 5) {
                throw ValidationException::withMessages([
                    'resolution_note' => 'Catatan penyelesaian wajib diisi saat ticket diselesaikan.',
                ]);
            }

            $oldStatus = $current->status;
            $oldAssigneeId = $current->assignee_id;
            $eventTime = now('UTC')->startOfSecond();
            $now = $eventTime->copy()->setTimezone(config('app.timezone'));

            $current->update([
                'status' => $status,
                'assignee_id' => $assigneeId,
                'resolution_note' => in_array($status, ['completed', 'closed'], true) ? trim($resolutionNote) : '',
                'updated_at' => $now,
            ]);

            if ($oldStatus !== $status || (int) $oldAssigneeId !== (int) $assigneeId) {
                $this->recordStateChange(
                    $current->fresh(),
                    $actor,
                    $oldStatus,
                    $status,
                    $oldAssigneeId,
                    $assigneeId,
                    $eventTime,
                    'ticket.update',
                );
            }

            if ($oldStatus !== $status) {
                $this->activity($current, $actor, 'Status diubah menjadi '.(config('helpdesk.statuses')[$status] ?? $status), $now);
            }
            if ((int) $oldAssigneeId !== (int) $assigneeId) {
                $assignee = $assigneeId ? User::find($assigneeId) : null;
                $this->activity($current, $actor, $assignee ? 'Ditugaskan kepada '.$assignee->name : 'Assignment dilepas', $now);
            }
        });
    }

    public function resolveByRequester(Ticket $ticket, User $requester, string $decision): void
    {
        DB::transaction(function () use ($ticket, $requester, $decision): void {
            $current = Ticket::query()->lockForUpdate()->findOrFail($ticket->getKey());
            if ((int) $requester->id !== (int) $current->requester_id
                || $current->status !== 'completed'
                || ! in_array($decision, ['close', 'reopen'], true)) {
                throw new AuthorizationException('Keputusan requester tidak diizinkan untuk ticket ini.');
            }

            $eventTime = now('UTC')->startOfSecond();
            $now = $eventTime->copy()->setTimezone(config('app.timezone'));
            $status = $decision === 'close' ? 'closed' : 'in_progress';
            $message = $decision === 'close'
                ? 'Requester mengonfirmasi masalah selesai dan menutup ticket'
                : 'Requester melaporkan masalah belum selesai dan membuka kembali ticket';

            $current->update([
                'status' => $status,
                'resolution_note' => $status === 'in_progress' ? '' : $current->resolution_note,
                'updated_at' => $now,
            ]);
            $this->recordStateChange(
                $current->fresh(),
                $requester,
                'completed',
                $status,
                $current->assignee_id,
                $current->assignee_id,
                $eventTime,
                'ticket.requester_resolution',
            );
            $this->activity($current, $requester, $message, $now);
        });
    }

    private function authorizeUpdate(Ticket $ticket, User $actor): void
    {
        if ($actor->role === 'admin') {
            return;
        }

        if ($actor->role !== 'it' || (int) $ticket->assignee_id !== (int) $actor->id) {
            throw new AuthorizationException('Aktor tidak berwenang memperbarui ticket ini.');
        }
    }

    private function recordStateChange(
        Ticket $ticket,
        User $actor,
        string $fromStatus,
        string $toStatus,
        ?int $fromAssigneeId,
        ?int $toAssigneeId,
        $eventTime,
        string $source,
    ): void {
        $commandId = (string) Str::uuid();
        $cycle = $this->latestResolutionCycle($ticket);
        $attributes = [
            'command_id' => $commandId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'from_assignee_id' => $fromAssigneeId,
            'to_assignee_id' => $toAssigneeId,
        ];

        if ($fromStatus === $toStatus) {
            $eventType = 'ticket.assignment_changed';
            $eventKey = 'command:'.$commandId;
        } elseif ($toStatus === 'completed') {
            $hasCreationEvent = $ticket->events()->where('event_type', 'ticket.created')->exists();
            $cycle = $this->latestResolutionCycle($ticket) + 1;
            if (! $hasCreationEvent && $cycle === 1) {
                $eventType = 'ticket.resolution_recorded';
                $eventKey = 'command:'.$commandId;
                $cycle = null;
            } else {
                $eventType = $cycle === 1 ? 'ticket.first_resolution' : 'ticket.final_resolution';
                $eventKey = $cycle === 1 ? 'ticket.first_resolution' : 'ticket.resolution.'.$cycle;
                $attributes['resolution_cycle'] = $cycle;
            }
        } elseif ($fromStatus === 'completed' && $toStatus === 'in_progress') {
            $eventType = 'ticket.reopened';
            $eventKey = $cycle > 0 ? 'ticket.reopened.'.$cycle : 'command:'.$commandId;
            $attributes['resolution_cycle'] = $cycle > 0 ? $cycle : null;
        } elseif ($toStatus === 'closed') {
            $eventType = 'ticket.closed';
            $eventKey = $cycle > 0 ? 'ticket.closed.'.$cycle : 'command:'.$commandId;
            $attributes['resolution_cycle'] = $cycle > 0 ? $cycle : null;
        } elseif ($toStatus === 'cancelled') {
            $eventType = 'ticket.cancelled';
            $eventKey = 'ticket.cancelled';
        } else {
            $eventType = 'ticket.status_changed';
            $eventKey = 'command:'.$commandId;
        }

        $this->eventRecorder->record(
            $ticket,
            $eventKey,
            $eventType,
            $actor,
            $source,
            $eventTime,
            $attributes,
        );
    }

    private function latestResolutionCycle(Ticket $ticket): int
    {
        return (int) $ticket->events()
            ->whereIn('event_type', ['ticket.first_resolution', 'ticket.final_resolution'])
            ->max('resolution_cycle');
    }

    public function markAsRead(Ticket $ticket, User $user, $at = null): void
    {
        TicketRead::query()->updateOrCreate(
            ['ticket_id' => $ticket->id, 'user_id' => $user->id],
            ['last_read_at' => $at ?: now()],
        );
    }

    public function markAllAsRead(User $user): void
    {
        $readAt = now();

        DB::transaction(function () use ($user, $readAt): void {
            Ticket::query()
                ->select('id')
                ->when(
                    ! in_array($user->role, ['it', 'admin'], true),
                    fn ($query) => $query->where('requester_id', $user->id),
                )
                ->chunkById(500, function ($tickets) use ($user, $readAt): void {
                    $rows = $tickets->map(fn (Ticket $ticket) => [
                        'ticket_id' => $ticket->id,
                        'user_id' => $user->id,
                        'last_read_at' => $readAt,
                    ])->all();

                    TicketRead::query()->upsert(
                        $rows,
                        ['ticket_id', 'user_id'],
                        ['last_read_at'],
                    );
                });
        });
    }

    private function activity(Ticket $ticket, User $user, string $message, $at): Activity
    {
        return $ticket->activities()->create([
            'user_id' => $user->id,
            'message' => $message,
            'created_at' => $at,
        ]);
    }

    private function slaDeadline($createdAt, string $priority)
    {
        return $createdAt->copy()->addHours(config('helpdesk.sla_hours.'.$priority, 24));
    }

    private function storeFiles(array $files): array
    {
        $saved = [];
        $extensionByMime = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/heic' => 'heic',
            'image/heif' => 'heif',
            'image/heic-sequence' => 'heic',
            'image/heif-sequence' => 'heif',
        ];

        try {
            foreach ($files as $file) {
                if (! $file instanceof UploadedFile) {
                    continue;
                }

                $mime = $file->getMimeType();
                if (! isset($extensionByMime[$mime])) {
                    throw new \RuntimeException('Lampiran hanya boleh berupa JPG, PNG, WebP, HEIC, atau HEIF.');
                }

                $storedName = Str::random(32).'.'.$extensionByMime[$mime];
                if (! Storage::disk('ticket_attachments')->putFileAs('uploads', $file, $storedName)) {
                    throw new \RuntimeException('Screenshot tidak dapat disimpan.');
                }
                $saved[] = [
                    'disk' => 'ticket_attachments',
                    'stored_name' => $storedName,
                    'original_name' => basename($file->getClientOriginalName()),
                    'mime' => $mime,
                    'size' => (int) $file->getSize(),
                ];
            }
        } catch (\Throwable $error) {
            foreach ($saved as $file) {
                Storage::disk($file['disk'])->delete('uploads/'.$file['stored_name']);
            }
            throw $error;
        }

        return $saved;
    }
}
