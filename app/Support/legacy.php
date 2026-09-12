<?php

use App\Models\Activity;
use App\Models\Attachment;
use App\Models\Comment;
use App\Models\Ticket;
use App\Models\TicketRead;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

if (! function_exists('helpdesk_boot_legacy_constants')) {
    function helpdesk_boot_legacy_constants(): void
    {
        if (! defined('STATUSES')) {
            define('STATUSES', config('helpdesk.statuses'));
        }
        if (! defined('PRIORITIES')) {
            define('PRIORITIES', config('helpdesk.priorities'));
        }
        if (! defined('CATEGORIES')) {
            define('CATEGORIES', config('helpdesk.categories'));
        }
    }
}

helpdesk_boot_legacy_constants();

if (! function_exists('readData')) {
    function readData(): array
    {
        $users = User::query()->orderBy('id')->get()->map(fn (User $user) => [
            'id' => (int) $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'department' => $user->department ?: '-',
            'job_title' => $user->job_title ?: ($user->role === 'it' ? 'IT Support' : 'Karyawan'),
            'active' => (bool) $user->active,
        ])->all();

        $tickets = Ticket::query()->orderBy('id')->get()->map(function (Ticket $ticket): array {
            return [
                'id' => (int) $ticket->id,
                'subject' => $ticket->subject,
                'description' => $ticket->description,
                'category' => $ticket->category,
                'priority' => $ticket->priority,
                'status' => $ticket->status,
                'requester_id' => (int) $ticket->requester_id,
                'assignee_id' => $ticket->assignee_id === null ? null : (int) $ticket->assignee_id,
                'resolution_note' => $ticket->resolution_note ?? '',
                'created_at' => $ticket->created_at?->toDateTimeString(),
                'updated_at' => $ticket->updated_at?->toDateTimeString(),
                'sla_deadline' => $ticket->sla_deadline?->toDateTimeString(),
            ];
        })->all();

        return [
            'users' => $users,
            'tickets' => $tickets,
            'comments' => Comment::query()->orderBy('id')->get()->map(fn (Comment $comment) => [
                'id' => (int) $comment->id,
                'ticket_id' => (int) $comment->ticket_id,
                'user_id' => (int) $comment->user_id,
                'message' => $comment->message,
                'created_at' => $comment->created_at?->toDateTimeString(),
            ])->all(),
            'activities' => Activity::query()->orderBy('id')->get()->map(fn (Activity $activity) => [
                'id' => (int) $activity->id,
                'ticket_id' => (int) $activity->ticket_id,
                'user_id' => (int) $activity->user_id,
                'message' => $activity->message,
                'created_at' => $activity->created_at?->toDateTimeString(),
            ])->all(),
            'attachments' => Attachment::query()->orderBy('id')->get()->map(fn (Attachment $attachment) => [
                'id' => (int) $attachment->id,
                'ticket_id' => (int) $attachment->ticket_id,
                'user_id' => (int) $attachment->user_id,
                'stored_name' => $attachment->stored_name,
                'original_name' => $attachment->original_name,
                'mime' => $attachment->mime,
                'size' => (int) $attachment->size,
                'created_at' => $attachment->created_at?->toDateTimeString(),
            ])->all(),
            'reads' => TicketRead::query()->orderBy('ticket_id')->orderBy('user_id')->get()->map(fn (TicketRead $read) => [
                'ticket_id' => (int) $read->ticket_id,
                'user_id' => (int) $read->user_id,
                'last_read_at' => $read->last_read_at?->toDateTimeString(),
            ])->all(),
        ];
    }
}

if (! function_exists('currentUser')) {
    function currentUser(array $data): ?array
    {
        $id = Auth::id();
        foreach ($data['users'] as $user) {
            if ((int) $user['id'] === (int) $id && $user['active']) {
                return $user;
            }
        }

        return null;
    }
}

if (! function_exists('userById')) {
    function userById(array $data, ?int $id): ?array
    {
        foreach ($data['users'] as $user) {
            if ((int) $user['id'] === (int) $id) {
                return $user;
            }
        }

        return null;
    }
}

if (! function_exists('canView')) {
    function canView(array $ticket, array $user): bool
    {
        return in_array($user['role'], ['it', 'admin'], true)
            || (int) $ticket['requester_id'] === (int) $user['id'];
    }
}

if (! function_exists('allowedStatusTransitions')) {
    function allowedStatusTransitions(string $status): array
    {
        return config('helpdesk.status_transitions.'.$status, [$status]);
    }
}

if (! function_exists('fmtDate')) {
    function fmtDate(?string $date): string
    {
        return $date ? date('d M Y, H:i', strtotime($date)) : '-';
    }
}

if (! function_exists('relativeDate')) {
    function relativeDate(?string $date): string
    {
        if (! $date) {
            return '-';
        }

        $diff = time() - strtotime($date);
        if ($diff < 60) {
            return 'baru saja';
        }
        if ($diff < 3600) {
            return floor($diff / 60).' menit lalu';
        }
        if ($diff < 86400) {
            return floor($diff / 3600).' jam lalu';
        }

        return floor($diff / 86400).' hari lalu';
    }
}

if (! function_exists('slaHours')) {
    function slaHours(): array
    {
        return config('helpdesk.sla_hours');
    }
}

if (! function_exists('calcSlaDeadline')) {
    function calcSlaDeadline(string $createdAt, string $priority): string
    {
        $hours = slaHours()[$priority] ?? 24;

        return date('Y-m-d H:i:s', strtotime($createdAt) + ($hours * 3600));
    }
}

if (! function_exists('slaStatus')) {
    function slaStatus(array $ticket): ?array
    {
        if (empty($ticket['sla_deadline']) || in_array($ticket['status'], ['closed', 'cancelled', 'completed'], true)) {
            return null;
        }

        $diff = strtotime($ticket['sla_deadline']) - time();
        $hours = slaHours()[$ticket['priority']] ?? 24;
        $warningThreshold = $hours * 3600 * 0.25;

        if ($diff <= 0) {
            return ['status' => 'breached', 'label' => 'SLA Terlampaui', 'seconds_left' => $diff];
        }
        if ($diff <= $warningThreshold) {
            $minutes = (int) ceil($diff / 60);

            return [
                'status' => 'warning',
                'label' => $minutes >= 60 ? ceil($diff / 3600).' jam lagi' : $minutes.' menit lagi',
                'seconds_left' => $diff,
            ];
        }

        return ['status' => 'ok', 'label' => 'SLA: '.ceil($diff / 3600).' jam', 'seconds_left' => $diff];
    }
}

if (! function_exists('csrf')) {
    function csrf(): string
    {
        return csrf_token();
    }
}
