<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class TicketEvent extends Model
{
    public $timestamps = false;

    protected $table = 'ticket_events';

    protected $fillable = [
        'ticket_id',
        'event_key',
        'command_id',
        'event_ordinal',
        'event_type',
        'occurred_at_utc',
        'recorded_at_utc',
        'actor_kind',
        'actor_id',
        'actor_role_snapshot',
        'source',
        'reason',
        'from_status',
        'to_status',
        'from_priority',
        'to_priority',
        'from_assignee_id',
        'to_assignee_id',
        'comment_id',
        'wait_interval_key',
        'resolution_cycle',
    ];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Ticket events are append-only.');
        });

        static::deleting(static function (): never {
            throw new LogicException('Ticket events are append-only.');
        });
    }

    protected function casts(): array
    {
        return [
            'occurred_at_utc' => UtcDateTime::class,
            'recorded_at_utc' => UtcDateTime::class,
            'event_ordinal' => 'integer',
            'resolution_cycle' => 'integer',
        ];
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function comment()
    {
        return $this->belongsTo(Comment::class);
    }
}
