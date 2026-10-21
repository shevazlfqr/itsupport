<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    public $timestamps = false;

    protected $table = 'attachments';

    protected $fillable = [
        'ticket_id',
        'user_id',
        'disk',
        'stored_name',
        'original_name',
        'mime',
        'size',
        'created_at',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'size' => 'integer'];
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
