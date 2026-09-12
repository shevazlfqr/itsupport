<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_login_screen_is_available(): void
    {
        $response = $this->get('/login');

        $response->assertOk()->assertSee('Masuk ke akunmu');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }
}
