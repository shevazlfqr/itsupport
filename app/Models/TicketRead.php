<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketRead extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $table = 'ticket_reads';

    protected $fillable = ['ticket_id', 'user_id', 'last_read_at'];

    protected function casts(): array
    {
        return ['last_read_at' => 'datetime'];
    }
}
