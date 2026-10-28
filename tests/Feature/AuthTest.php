<?php

namespace Tests\Feature;

use App\Enums\ChronicleRole;
use App\Models\Chronicle;
use App\Models\ChronicleMember;
use App\Models\User;
use Tests\Support\JoinsChronicle;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use JoinsChronicle;

    public function test_registration_does_not_assign_global_role_or_memberships(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Первый',
            'login' => 'first_user',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('user.memberships', []);
        $response->assertJsonStructure(['token', 'user' => ['id', 'name', 'login', 'memberships']]);
        $response->assertJsonMissingPath('user.role');
        $response->assertJsonMissingPath('user.is_storyteller');

        $this->assertDatabaseHas('users', [
            'login' => 'first_user',
        ]);
        $this->assertDatabaseCount('chronicle_members', 0);
    }

    public function test_second_registered_user_also_has_empty_memberships(): void
    {
        User::factory()->create();

        $response = $this->postJson('/api/register', [
            'name' => 'Второй',
            'login' => 'second_user',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('user.memberships', []);
        $response->assertJsonMissingPath('user.role');
        $response->assertJsonMissingPath('user.is_storyteller');
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

    public function test_user_endpoint_includes_active_memberships(): void
    {
        $user = User::factory()->create();
        $chronicle = Chronicle::factory()->create([
            'title' => 'Хроника пользователя',
            'created_by' => $user->id,
        ]);
        $member = $this->joinChronicle($user, $chronicle, ChronicleRole::Storyteller);

        $archivedChronicle = Chronicle::factory()->create([
            'title' => 'Завершённая хроника',
            'created_by' => $user->id,
        ]);
        ChronicleMember::factory()
            ->for($archivedChronicle)
            ->for($user)
            ->player()
            ->left()
            ->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/user');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'user.memberships');
        $response->assertJsonPath('user.memberships.0.chronicle_id', $chronicle->id);
        $response->assertJsonPath('user.memberships.0.chronicle_title', 'Хроника пользователя');
        $response->assertJsonPath('user.memberships.0.role', ChronicleRole::Storyteller->value);
        $response->assertJsonPath('user.memberships.0.joined_at', $member->joined_at->toIso8601String());
    }
}
