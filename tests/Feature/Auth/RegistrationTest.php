<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'start_work_date' => '2026-06-01',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();

        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('2026-06-01', $user->start_work_date->toDateString());

        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_registration_requires_a_start_work_date(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'missing-date@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('start_work_date');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'missing-date@example.com']);
    }

    public function test_registration_accepts_future_start_work_date(): void
    {
        $futureDate = today()->addDay()->toDateString();

        $response = $this->post('/register', [
            'name' => 'Future User',
            'email' => 'future@example.com',
            'start_work_date' => $futureDate,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'future@example.com',
            'start_work_date' => $futureDate,
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
    }
}
