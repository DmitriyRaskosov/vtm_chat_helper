<?php

namespace Tests\Feature;

use App\Enums\GameSessionStatus;
use App\Models\Chronicle;
use App\Models\GameSession;
use App\Models\User;
use Database\Seeders\CanonClanSeeder;
use Database\Seeders\CanonSectSeeder;
use Tests\TestCase;

class MessageTest extends TestCase
{
    private User $storyteller;

    private Chronicle $chronicle;

    private GameSession $gameSession;

    private int $sceneId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CanonSectSeeder::class);
        $this->seed(CanonClanSeeder::class);

        $this->storyteller = User::factory()->storyteller()->create();
        $this->chronicle = Chronicle::query()->create([
            'title' => 'Тестовая хроника',
            'created_by' => $this->storyteller->id,
        ]);
        $this->gameSession = GameSession::query()->create([
            'chronicle_id' => $this->chronicle->id,
            'title' => 'Тестовая сессия',
            'status' => GameSessionStatus::Active,
            'created_by' => $this->storyteller->id,
        ]);

        $created = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/game-sessions/'.$this->gameSession->id.'/scenes', [
                'title' => 'Сцена',
            ]);
        $created->assertStatus(201);
        $this->sceneId = (int) $created->json('scene.id');

        $this->actingAs($this->storyteller, 'sanctum')
            ->patchJson("/api/scenes/{$this->sceneId}/activate")
            ->assertStatus(200);
    }

    public function test_user_can_send_message(): void
    {
        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/messages', [
                'body' => 'Привет от мастера',
                'scene_id' => $this->sceneId,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('message.body', 'Привет от мастера');
        $this->assertDatabaseHas('messages', [
            'scene_id' => $this->sceneId,
            'body' => 'Привет от мастера',
            'user_id' => $this->storyteller->id,
        ]);
    }

    public function test_message_requires_active_scene(): void
    {
        $second = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/game-sessions/'.$this->gameSession->id.'/scenes', [
                'title' => 'Черновик',
                'activate' => false,
            ]);
        $second->assertStatus(201);
        $secondSceneId = $second->json('scene.id');

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/messages', [
                'body' => 'X',
                'scene_id' => $secondSceneId,
            ]);

        $response->assertStatus(409);
        $this->assertDatabaseMissing('messages', [
            'body' => 'X',
        ]);
    }

    public function test_messages_returns_scene_history(): void
    {
        foreach (['Первое', 'Второе'] as $body) {
            $this->actingAs($this->storyteller, 'sanctum')
                ->postJson('/api/messages', [
                    'body' => $body,
                    'scene_id' => $this->sceneId,
                ])
                ->assertStatus(201);
        }

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->getJson('/api/messages?scene_id='.$this->sceneId);

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'messages');
        $response->assertJsonPath('messages.0.body', 'Первое');
        $response->assertJsonPath('messages.1.body', 'Второе');
    }
}
