<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Comment;
use App\Models\Ticket;
use App\Models\TicketRead;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TicketService
{
    public function create(User $requester, array $attributes, array $files = []): Ticket
    {
        return DB::transaction(function () use ($requester, $attributes, $files): Ticket {
            $now = now();
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
                    Storage::disk('public')->delete('uploads/'.$file['stored_name']);
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
            $now = now();
            $comment = $ticket->comments()->create([
                'user_id' => $user->id,
                'message' => trim($message),
                'created_at' => $now,
            ]);

            $ticket->update(['updated_at' => $now]);
            $this->markAsRead($ticket, $user, $now);
            $this->activity($ticket, $user, 'Menambahkan komentar', $now);

            return $comment;
        });
    }

    public function claim(Ticket $ticket, User $user): void
    {
        DB::transaction(function () use ($ticket, $user): void {
            $now = now();
            $ticket->update([
                'assignee_id' => $user->id,
                'status' => 'assigned',
                'updated_at' => $now,
            ]);
            $this->activity($ticket, $user, 'Mengambil ticket dan menjadi teknisi yang ditugaskan', $now);
        });
    }

    public function update(Ticket $ticket, User $actor, string $status, ?int $assigneeId, string $resolutionNote): void
    {
        DB::transaction(function () use ($ticket, $actor, $status, $assigneeId, $resolutionNote): void {
            $oldStatus = $ticket->status;
            $oldAssigneeId = $ticket->assignee_id;
            $now = now();

            $ticket->update([
                'status' => $status,
                'assignee_id' => $assigneeId,
                'resolution_note' => in_array($status, ['completed', 'closed'], true) ? trim($resolutionNote) : '',
                'updated_at' => $now,
            ]);

            if ($oldStatus !== $status) {
                $this->activity($ticket, $actor, 'Status diubah menjadi '.(config('helpdesk.statuses')[$status] ?? $status), $now);
            }
            if ((int) $oldAssigneeId !== (int) $assigneeId) {
                $assignee = $assigneeId ? User::find($assigneeId) : null;
                $this->activity($ticket, $actor, $assignee ? 'Ditugaskan kepada '.$assignee->name : 'Assignment dilepas', $now);
            }
        });
    }

    public function resolveByRequester(Ticket $ticket, User $requester, string $decision): void
    {
        DB::transaction(function () use ($ticket, $requester, $decision): void {
            $now = now();
            $status = $decision === 'close' ? 'closed' : 'in_progress';
            $message = $decision === 'close'
                ? 'Requester mengonfirmasi masalah selesai dan menutup ticket'
                : 'Requester melaporkan masalah belum selesai dan membuka kembali ticket';

            $ticket->update([
                'status' => $status,
                'resolution_note' => $status === 'in_progress' ? '' : $ticket->resolution_note,
                'updated_at' => $now,
            ]);
            $this->activity($ticket, $requester, $message, $now);
        });
    }

    public function markAsRead(Ticket $ticket, User $user, $at = null): void
    {
        TicketRead::query()->updateOrCreate(
            ['ticket_id' => $ticket->id, 'user_id' => $user->id],
            ['last_read_at' => $at ?: now()],
        );
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
                if (! Storage::disk('public')->putFileAs('uploads', $file, $storedName)) {
                    throw new \RuntimeException('Screenshot tidak dapat disimpan.');
                }
                $saved[] = [
                    'stored_name' => $storedName,
                    'original_name' => basename($file->getClientOriginalName()),
                    'mime' => $mime,
                    'size' => (int) $file->getSize(),
                ];
            }
        } catch (\Throwable $error) {
            foreach ($saved as $file) {
                Storage::disk('public')->delete('uploads/'.$file['stored_name']);
            }
            throw $error;
        }

        return $saved;
    }
}
