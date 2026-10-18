<?php

namespace Tests\Feature;

use App\Enums\ChronicleRole;
use App\Enums\GameSessionStatus;
use App\Enums\SceneStatus;
use App\Models\Chronicle;
use App\Models\GameSession;
use App\Models\Scene;
use App\Models\SceneContext;
use App\Models\User;
use Database\Seeders\CanonClanSeeder;
use Database\Seeders\CanonSectSeeder;
use Tests\Support\JoinsChronicle;
use Tests\TestCase;

class GameSessionTest extends TestCase
{
    use JoinsChronicle;

    private User $storyteller;

    private Chronicle $chronicle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CanonSectSeeder::class);
        $this->seed(CanonClanSeeder::class);

        $this->storyteller = User::factory()->storyteller()->create();
        $this->chronicle = Chronicle::factory()->create([
            'title' => 'Тестовая хроника',
            'created_by' => $this->storyteller->id,
        ]);
        $this->joinChronicle($this->storyteller, $this->chronicle, ChronicleRole::Storyteller);
    }

    public function test_active_without_session_returns_null(): void
    {
        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->getJson($this->chronicleApi($this->chronicle, 'game-sessions/active'));

        $response->assertStatus(200);
        $response->assertJsonPath('game_session', null);
    }

    public function test_store_archives_previous_active_session_and_closes_scenes(): void
    {
        $previous = GameSession::factory()->active()->create([
            'chronicle_id' => $this->chronicle->id,
            'title' => 'Старая сессия',
            'created_by' => $this->storyteller->id,
        ]);
        $openScene = Scene::factory()->create([
            'game_session_id' => $previous->id,
            'status' => SceneStatus::Active,
            'position' => 1,
        ]);

        $this->actingAs($this->storyteller, 'sanctum')
            ->putJson("/api/scenes/{$openScene->id}/context", [
                'expected_revision' => 0,
                'situation' => 'До архивации',
            ])
            ->assertStatus(200);

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'game-sessions'), [
                'title' => 'Новая сессия',
            ]);

        $response->assertStatus(201);
        $newSessionId = (int) $response->json('game_session.id');

        $this->assertDatabaseHas('game_sessions', [
            'id' => $previous->id,
            'status' => GameSessionStatus::Archived->value,
        ]);
        $this->assertDatabaseHas('scenes', [
            'id' => $openScene->id,
            'status' => SceneStatus::Closed->value,
        ]);
        $this->assertDatabaseHas('scene_contexts', [
            'scene_id' => $openScene->id,
        ]);
        $this->assertNotNull(
            SceneContext::query()->where('scene_id', $openScene->id)->value('frozen_revision'),
        );

        $response->assertJsonPath('game_session.status', GameSessionStatus::Active->value);
        $response->assertJsonPath('game_session.scenes.0.title', 'Начальная сцена');
        $response->assertJsonPath('game_session.scenes.0.status', SceneStatus::Active->value);

        $this->assertDatabaseHas('game_sessions', [
            'id' => $newSessionId,
            'status' => GameSessionStatus::Active->value,
            'title' => 'Новая сессия',
        ]);
    }

    public function test_cannot_add_scene_to_archived_session(): void
    {
        $archived = GameSession::factory()->create([
            'chronicle_id' => $this->chronicle->id,
            'title' => 'Архивная сессия',
            'status' => GameSessionStatus::Archived,
            'created_by' => $this->storyteller->id,
        ]);

        $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/game-sessions/'.$archived->id.'/scenes', [
                'title' => 'Лишняя сцена',
            ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Scenes can only be added to the active game session.');
    }
}
