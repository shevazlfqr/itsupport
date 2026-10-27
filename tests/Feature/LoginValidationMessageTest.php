<?php

namespace Tests\Feature;

use Tests\TestCase;

class LoginValidationMessageTest extends TestCase
{
    public function test_empty_login_fields_show_readable_messages(): void
    {
        $this->from('/login')
            ->post(route('login.store'), [])
            ->assertRedirect('/login')
            ->assertSessionHasErrors([
                'email' => 'Email kantor wajib diisi.',
                'password' => 'Password wajib diisi.',
            ]);
    }

    public function test_invalid_email_shows_readable_message(): void
    {
        $this->from('/login')
            ->post(route('login.store'), [
                'email' => 'bukan-email',
                'password' => 'test-only',
            ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors([
                'email' => 'Masukkan email kantor yang valid.',
            ]);
    }
}
