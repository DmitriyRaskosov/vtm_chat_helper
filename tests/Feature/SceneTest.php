<?php

namespace Tests\Feature;

use App\Enums\GameSessionStatus;
use App\Models\Chronicle;
use App\Models\GameSession;
use App\Models\User;
use Database\Seeders\CanonClanSeeder;
use Database\Seeders\CanonSectSeeder;
use Tests\TestCase;

class SceneTest extends TestCase
{
    private User $storyteller;

    private Chronicle $chronicle;

    private GameSession $gameSession;

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
    }

    public function test_storyteller_can_create_scene(): void
    {
        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/game-sessions/'.$this->gameSession->id.'/scenes', [
                'title' => 'Первая сцена',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('scenes', [
            'id' => $response->json('scene.id'),
            'game_session_id' => $this->gameSession->id,
        ]);
    }

    public function test_scene_can_be_activated_and_closed(): void
    {
        $sceneId = $this->createScene();

        $closed = $this->actingAs($this->storyteller, 'sanctum')
            ->patchJson("/api/scenes/{$sceneId}/close");

        $closed->assertStatus(200);
        $closed->assertJsonPath('scene.status', 'closed');
    }

    public function test_closed_scene_can_be_activated_again(): void
    {
        $firstId = $this->createScene();

        $this->actingAs($this->storyteller, 'sanctum')
            ->putJson("/api/scenes/{$firstId}/context", [
                'expected_revision' => 0,
                'situation' => 'Темно',
            ])
            ->assertStatus(200);

        $this->actingAs($this->storyteller, 'sanctum')
            ->patchJson("/api/scenes/{$firstId}/close")
            ->assertStatus(200);

        $secondId = $this->createScene();

        $reopened = $this->actingAs($this->storyteller, 'sanctum')
            ->patchJson("/api/scenes/{$firstId}/activate");

        $reopened->assertStatus(200);
        $reopened->assertJsonPath('scene.status', 'active');
        $reopened->assertJsonPath('scene.ended_at', null);

        $this->assertDatabaseHas('scenes', [
            'id' => $secondId,
            'status' => 'draft',
        ]);
        $this->assertDatabaseHas('scene_contexts', [
            'scene_id' => $firstId,
            'frozen_revision' => null,
        ]);
    }

    public function test_storyteller_can_add_participant(): void
    {
        $npc = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/characters', [
                'canonical_name' => 'Иван',
                'character_type' => 'npc',
                'chronicle_id' => $this->chronicle->id,
            ]);
        $npc->assertStatus(201);
        $npcId = $npc->json('character.id');

        $sceneId = $this->createScene();

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson("/api/scenes/{$sceneId}/participants", [
                'character_id' => $npcId,
                'role' => 'npc',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('scene_participants', [
            'scene_id' => $sceneId,
            'character_id' => $npcId,
        ]);
    }

    public function test_participant_must_belong_to_same_chronicle(): void
    {
        $otherChronicle = Chronicle::query()->create([
            'title' => 'Другая хроника',
            'created_by' => $this->storyteller->id,
        ]);
        GameSession::query()->create([
            'chronicle_id' => $otherChronicle->id,
            'title' => 'Другая сессия',
            'status' => GameSessionStatus::Active,
            'created_by' => $this->storyteller->id,
        ]);

        $npc = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/characters', [
                'canonical_name' => 'Чужой',
                'character_type' => 'npc',
                'chronicle_id' => $otherChronicle->id,
            ]);
        $npc->assertStatus(201);

        $sceneId = $this->createScene();

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson("/api/scenes/{$sceneId}/participants", [
                'character_id' => $npc->json('character.id'),
                'role' => 'npc',
            ]);

        $response->assertStatus(422);
    }

    public function test_scene_context_can_be_updated(): void
    {
        $sceneId = $this->createScene();

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->putJson("/api/scenes/{$sceneId}/context", [
                'expected_revision' => 0,
                'atmosphere' => 'Туман над набережной',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('scene_contexts', [
            'scene_id' => $sceneId,
        ]);
    }

    public function test_scene_context_rejects_stale_revision(): void
    {
        $sceneId = $this->createScene();

        $this->actingAs($this->storyteller, 'sanctum')
            ->putJson("/api/scenes/{$sceneId}/context", [
                'expected_revision' => 0,
                'situation' => 'Темно',
            ])
            ->assertStatus(200);

        $this->actingAs($this->storyteller, 'sanctum')
            ->putJson("/api/scenes/{$sceneId}/context", [
                'expected_revision' => 0,
                'situation' => 'Светло',
            ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Сцена закрыта или локация уже изменена');

        $this->assertDatabaseHas('scene_contexts', [
            'scene_id' => $sceneId,
            'situation' => 'Темно',
        ]);
    }

    public function test_closed_scene_context_cannot_be_updated(): void
    {
        $sceneId = $this->createScene();

        $this->actingAs($this->storyteller, 'sanctum')
            ->patchJson("/api/scenes/{$sceneId}/close")
            ->assertStatus(200);

        $this->actingAs($this->storyteller, 'sanctum')
            ->putJson("/api/scenes/{$sceneId}/context", [
                'expected_revision' => 0,
                'situation' => 'Темно',
            ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Сцена закрыта или локация уже изменена');
    }

    private function createScene(): int
    {
        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/game-sessions/'.$this->gameSession->id.'/scenes', [
                'title' => 'Сцена',
            ]);

        $response->assertStatus(201);

        return (int) $response->json('scene.id');
    }
}
