<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_read_messages(): void
    {
        $this->getJson('/api/messages')->assertUnauthorized();
    }

    public function test_user_can_register_with_any_login(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Рассказчик',
            'login' => 'st-1',
            'password' => 'ab',
            'password_confirmation' => 'ab',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.login', 'st-1')
            ->assertJsonPath('user.role', UserRole::Storyteller->value)
            ->assertJsonPath('user.is_storyteller', true)
            ->assertJsonStructure(['token', 'user']);

        $this->assertDatabaseHas('users', [
            'login' => 'st-1',
            'role' => UserRole::Storyteller->value,
        ]);
    }

    public function test_second_user_is_a_player(): void
    {
        User::factory()->storyteller()->create();

        $this->postJson('/api/register', [
            'name' => 'Игрок',
            'login' => 'vampire.pc',
            'password' => 'ab',
            'password_confirmation' => 'ab',
        ])->assertCreated()
            ->assertJsonPath('user.role', UserRole::Player->value)
            ->assertJsonPath('user.is_storyteller', false);
    }

    public function test_user_can_login_by_login(): void
    {
        $user = User::factory()->create([
            'login' => 'player1',
        ]);

        $this->postJson('/api/login', [
            'login' => 'player1',
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('user.login', 'player1')
            ->assertJsonStructure(['token']);
    }

    public function test_user_can_logout_and_token_stops_working(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('spa')->plainTextToken;

        $this->withToken($token)->postJson('/api/logout')->assertNoContent();

        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_authenticated_user_can_fetch_profile(): void
    {
        $user = User::factory()->storyteller()->create(['name' => 'СТ']);

        Sanctum::actingAs($user);

        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('user.name', 'СТ')
            ->assertJsonPath('user.is_storyteller', true);
    }
}
