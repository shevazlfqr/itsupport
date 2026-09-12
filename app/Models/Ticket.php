<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    protected $table = 'tickets';

    protected $fillable = [
        'subject',
        'description',
        'category',
        'priority',
        'status',
        'requester_id',
        'assignee_id',
        'resolution_note',
        'created_at',
        'updated_at',
        'sla_deadline',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'sla_deadline' => 'datetime',
        ];
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class)->orderBy('created_at');
    }

    public function activities()
    {
        return $this->hasMany(Activity::class)->latest('created_at');
    }

    public function attachments()
    {
        return $this->hasMany(Attachment::class)->latest('created_at');
    }
}
