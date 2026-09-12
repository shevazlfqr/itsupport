<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'min:5', 'max:100'],
            'description' => ['required', 'string', 'min:10'],
            'category' => ['required', Rule::in(config('helpdesk.categories'))],
            'priority' => ['required', Rule::in(array_keys(config('helpdesk.priorities')))],
            'screenshots' => ['nullable', 'array', 'max:'.config('helpdesk.uploads.max_files')],
            'screenshots.*' => ['file', 'max:'.config('helpdesk.uploads.max_size_kb'), 'mimes:'.implode(',', config('helpdesk.uploads.mimes'))],
        ];
    }
}
