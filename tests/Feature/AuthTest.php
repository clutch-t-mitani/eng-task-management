<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'name' => 'Test Director',
            'email' => 'director@example.com',
            'password' => Hash::make('secret-password'),
        ]);

        $response = $this->fromSpa()->postJson('/api/v1/auth/login', [
            'email' => 'director@example.com',
            'password' => 'secret-password',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'id' => $user->id,
                'name' => 'Test Director',
                'email' => 'director@example.com',
            ])
            ->assertJsonMissing(['password']);

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'director@example.com',
            'password' => Hash::make('secret-password'),
        ]);

        $response = $this->fromSpa()->postJson('/api/v1/auth/login', [
            'email' => 'director@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable();
        $this->assertGuest();
    }

    public function test_authenticated_user_can_fetch_me(): void
    {
        $user = User::factory()->create([
            'name' => 'Test Director',
            'email' => 'director@example.com',
        ]);

        $response = $this->fromSpa()->actingAs($user)->getJson('/api/v1/auth/me');

        $response
            ->assertOk()
            ->assertJson([
                'id' => $user->id,
                'name' => 'Test Director',
                'email' => 'director@example.com',
            ]);
    }

    public function test_guest_cannot_fetch_me(): void
    {
        $this->fromSpa()->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_authenticated_user_can_logout(): void
    {
        User::factory()->create([
            'email' => 'director@example.com',
            'password' => Hash::make('secret-password'),
        ]);

        $this->fromSpa()->postJson('/api/v1/auth/login', [
            'email' => 'director@example.com',
            'password' => 'secret-password',
        ])->assertOk();

        $this->fromSpa()->postJson('/api/v1/auth/logout')->assertOk();
        $this->fromSpa()->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    private function fromSpa(): static
    {
        return $this->withHeader('Referer', 'http://localhost:5173');
    }
}
