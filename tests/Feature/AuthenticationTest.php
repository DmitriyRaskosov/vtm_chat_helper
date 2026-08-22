<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_sent_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/chat')->assertRedirect('/login');
    }

    public function test_user_can_register_with_any_login(): void
    {
        $response = $this->post('/register', [
            'name' => 'Рассказчик',
            'login' => 'st-1',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect('/chat');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'login' => 'st-1',
            'role' => UserRole::Storyteller->value,
        ]);
        $this->get('/chat')->assertOk()->assertSee('Панель рассказчика');
    }

    public function test_second_user_is_a_player_and_does_not_see_the_panel(): void
    {
        User::factory()->storyteller()->create();

        $this->post('/register', [
            'name' => 'Игрок',
            'login' => 'vampire.pc',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect('/chat');

        $this->assertDatabaseHas('users', [
            'login' => 'vampire.pc',
            'role' => UserRole::Player->value,
        ]);
        $this->get('/chat')
            ->assertOk()
            ->assertDontSee('Панель рассказчика');
    }

    public function test_user_can_login_by_login(): void
    {
        $user = User::factory()->create([
            'login' => 'player1',
        ]);

        $this->post('/login', [
            'login' => 'player1',
            'password' => 'password',
        ])->assertRedirect('/chat');

        $this->assertAuthenticatedAs($user);
    }
}
