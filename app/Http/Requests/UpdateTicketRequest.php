<?php

namespace App\Http\Requests;

use App\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ticket = $this->route('ticket') ?: Ticket::find($this->integer('ticket_id'));
        $user = $this->user();

        return $user && $ticket && ($user->role === 'admin' || ($user->role === 'it' && $ticket->assignee_id === $user->id));
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(array_keys(config('helpdesk.statuses')))],
            'assignee_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'it')->where('active', true))],
            'resolution_note' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
