<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebLoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_invalid_web_logins_are_throttled(): void
    {
        $payload = ['email' => 'missing@example.test', 'password' => 'wrong-password'];

        $this->get('/login')->assertOk();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', $payload)->assertStatus(302);
        }

        $this->post('/login', $payload)->assertStatus(429);
        $this->get('/login')->assertOk();
    }
}
