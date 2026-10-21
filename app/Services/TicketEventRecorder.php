<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Str;
use LogicException;

class TicketEventRecorder
{
    public function record(
        Ticket $ticket,
        string $eventKey,
        string $eventType,
        ?User $actor,
        string $source,
        DateTimeInterface $occurredAt,
        array $attributes = [],
    ): TicketEvent {
        $values = [
            'event_type' => $eventType,
            'actor_kind' => $actor ? 'user' : 'system',
            'actor_id' => $actor?->id,
            'actor_role_snapshot' => $actor?->role,
            'source' => $source,
            'reason' => $attributes['reason'] ?? null,
            'from_status' => $attributes['from_status'] ?? null,
            'to_status' => $attributes['to_status'] ?? null,
            'from_priority' => $attributes['from_priority'] ?? null,
            'to_priority' => $attributes['to_priority'] ?? null,
            'from_assignee_id' => $attributes['from_assignee_id'] ?? null,
            'to_assignee_id' => $attributes['to_assignee_id'] ?? null,
            'comment_id' => $attributes['comment_id'] ?? null,
            'wait_interval_key' => $attributes['wait_interval_key'] ?? null,
            'resolution_cycle' => $attributes['resolution_cycle'] ?? null,
        ];

        $existing = TicketEvent::query()
            ->where('ticket_id', $ticket->getKey())
            ->where('event_key', $eventKey)
            ->first();

        if ($existing) {
            foreach ($values as $name => $value) {
                if ($existing->getAttribute($name) != $value) {
                    throw new LogicException('Event key ini sudah digunakan untuk payload yang berbeda.');
                }
            }

            return $existing;
        }

        $commandId = $attributes['command_id'] ?? (string) Str::uuid();

        return TicketEvent::query()->create([
            'ticket_id' => $ticket->getKey(),
            'event_key' => $eventKey,
            'command_id' => $commandId,
            'event_ordinal' => $attributes['event_ordinal'] ?? 1,
            ...$values,
            'occurred_at_utc' => $occurredAt,
            'recorded_at_utc' => now('UTC'),
        ]);
    }
}
