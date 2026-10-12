<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Aymane Moutoute',
            'email' => 'aymane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token']);

        $this->assertDatabaseHas('users', ['email' => 'aymane@example.com']);
    }

    public function test_registration_validates_input(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Aymane',
            'email' => 'not-an-email',
            'password' => 'short',
            'password_confirmation' => 'different',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        User::factory()->create([
            'email' => 'aymane@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'aymane@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()->assertJsonStructure(['user', 'token']);
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        User::factory()->create(['email' => 'aymane@example.com']);

        $response = $this->postJson('/api/login', [
            'email' => 'aymane@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable();
    }

    public function test_authenticated_user_can_fetch_profile_and_logout(): void
    {
        $user = User::factory()->create();

        $me = $this->actingAs($user)->getJson('/api/me');
        $me->assertOk()->assertJsonPath('data.email', $user->email);

        $logout = $this->actingAs($user)->postJson('/api/logout');
        $logout->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
        ]);
    }

    public function test_guest_cannot_access_protected_routes(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
        $this->getJson('/api/cart')->assertUnauthorized();
        $this->getJson('/api/orders')->assertUnauthorized();
    }
}
