<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_first_registered_user_becomes_storyteller(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Первый',
            'login' => 'first_user',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('user.role', UserRole::Storyteller->value);
        $response->assertJsonPath('user.is_storyteller', true);
        $response->assertJsonStructure(['token', 'user' => ['id', 'name', 'login', 'role']]);

        $this->assertDatabaseHas('users', [
            'login' => 'first_user',
            'role' => UserRole::Storyteller->value,
        ]);
    }

    public function test_second_registered_user_becomes_player(): void
    {
        User::factory()->storyteller()->create();

        $response = $this->postJson('/api/register', [
            'name' => 'Второй',
            'login' => 'second_user',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('user.role', UserRole::Player->value);
        $response->assertJsonPath('user.is_storyteller', false);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'login' => 'test_login',
        ]);

        $response = $this->postJson('/api/login', [
            'login' => 'test_login',
            'password' => 'password',  // UserFactory ставит 'password'
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['token', 'user']);
        $response->assertJsonPath('user.id', $user->id);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create(['login' => 'test_login']);

        $response = $this->postJson('/api/login', [
            'login' => 'test_login',
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['login']);
    }

    public function test_authenticated_user_can_fetch_self(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/user');

        $response->assertStatus(200);
        $response->assertJsonPath('user.id', $user->id);
    }
}