<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:80'],
            'email' => ['required', 'email', 'max:120', 'unique:users,email'],
            'role' => ['required', Rule::in(array_keys(config('helpdesk.roles', ['employee' => true, 'it' => true, 'admin' => true])))],
            'job_title' => ['required', 'string', 'min:2', 'max:80'],
            'department' => ['required', 'string', 'min:2', 'max:80'],
            'password' => $this->passwordRules(),
        ];
    }

    private function passwordRules(): array
    {
        return [
            'required',
            'string',
            'min:8',
            'regex:/[A-Z]/',
            'regex:/[a-z]/',
            'regex:/[0-9]/',
            'regex:/[^A-Za-z0-9]/',
        ];
    }

    public function messages(): array
    {
        return [
            'password.regex' => 'Password harus memiliki huruf besar, huruf kecil, angka, dan karakter spesial.',
        ];
    }
}
