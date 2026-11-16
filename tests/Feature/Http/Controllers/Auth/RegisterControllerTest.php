<?php

namespace Tests\Feature\Http\Controllers\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_is_throttled_after_six_attempts_a_minute(): void
    {
        foreach (range(1, 6) as $attempt) {
            $this->post('/register', [])->assertSessionHasErrors('email');
        }

        $this->post('/register', [])->assertTooManyRequests();
    }
}
