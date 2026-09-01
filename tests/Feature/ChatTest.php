<?php

namespace Tests\Feature;

use App\Enums\SceneStatus;
use App\Models\GameSession;
use App\Models\Message;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_post_a_message_and_it_persists(): void
    {
        $user = User::factory()->create(['name' => 'Анна']);
        $scene = Scene::query()->active()->firstOrFail();

        Sanctum::actingAs($user);

        $this->postJson('/api/messages', [
            'body' => 'Каинит входит в Элизиум.',
            'scene_id' => $scene->id,
        ])
            ->assertCreated()
            ->assertJsonPath('message.body', 'Каинит входит в Элизиум.')
            ->assertJsonPath('message.scene_id', $scene->id)
            ->assertJsonPath('message.author', 'Анна');

        $this->assertDatabaseHas('messages', [
            'user_id' => $user->id,
            'scene_id' => $scene->id,
            'body' => 'Каинит входит в Элизиум.',
            'token_estimator_version' => 'unicode-chars-v1-cpt3',
        ]);
        $this->assertGreaterThan(0, Message::query()->firstOrFail()->token_estimate);

        $this->getJson('/api/messages')
            ->assertOk()
            ->assertJsonPath('messages.0.body', 'Каинит входит в Элизиум.');
    }

    public function test_messages_from_another_user_are_visible(): void
    {
        $anna = User::factory()->create(['name' => 'Анна']);
        $boris = User::factory()->create(['name' => 'Борис']);

        Message::factory()->create([
            'user_id' => $anna->id,
            'body' => 'Добрый вечер.',
        ]);

        Sanctum::actingAs($boris);

        $this->getJson('/api/messages')
            ->assertOk()
            ->assertJsonPath('messages.0.body', 'Добрый вечер.')
            ->assertJsonPath('messages.0.author', 'Анна')
            ->assertJsonPath('messages.0.mine', false);
    }

    public function test_storyteller_profile_flag_is_true(): void
    {
        $st = User::factory()->storyteller()->create(['name' => 'СТ']);

        Sanctum::actingAs($st);

        $this->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('user.is_storyteller', true);
    }

    public function test_message_lists_are_isolated_by_scene(): void
    {
        $user = User::factory()->create(['name' => 'Анна']);
        $session = GameSession::query()->active()->firstOrFail();
        $firstScene = $session->scenes()->firstOrFail();
        $secondScene = $session->scenes()->create([
            'position' => 2,
            'title' => 'Вторая сцена',
        ]);

        Message::factory()->create([
            'user_id' => $user->id,
            'scene_id' => $firstScene->id,
            'body' => 'Сообщение первой сцены.',
        ]);
        Message::factory()->create([
            'user_id' => $user->id,
            'scene_id' => $secondScene->id,
            'body' => 'Сообщение второй сцены.',
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/messages?scene_id={$firstScene->id}")
            ->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.body', 'Сообщение первой сцены.');

        $this->getJson("/api/messages?scene_id={$secondScene->id}")
            ->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.body', 'Сообщение второй сцены.');
    }

    public function test_closed_scene_is_readable_but_rejects_new_messages(): void
    {
        $user = User::factory()->create();
        $scene = Scene::query()->active()->firstOrFail();

        Message::factory()->create([
            'user_id' => $user->id,
            'scene_id' => $scene->id,
            'body' => 'Последняя реплика сцены.',
        ]);
        $scene->update([
            'status' => SceneStatus::Closed,
            'ended_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $this->getJson("/api/messages?scene_id={$scene->id}")
            ->assertOk()
            ->assertJsonPath('messages.0.body', 'Последняя реплика сцены.');

        $this->postJson('/api/messages', [
            'scene_id' => $scene->id,
            'body' => 'Слишком поздно.',
        ])->assertConflict();
    }
}
