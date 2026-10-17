<?php

namespace Tests\Feature;

use App\Models\Chronicle;
use App\Models\GameSession;
use App\Models\User;
use Database\Seeders\CanonClanSeeder;
use Database\Seeders\CanonSectSeeder;
use Tests\TestCase;

class SceneAuthorizationTest extends TestCase
{
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
    }

    public function test_player_cannot_create_game_session(): void
    {
        $player = User::factory()->create();

        $this->actingAs($player, 'sanctum')
            ->postJson('/api/game-sessions', ['title' => 'Сессия'])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Только рассказчик.');
    }

    public function test_player_cannot_close_scene(): void
    {
        $gameSession = GameSession::factory()->active()->create([
            'chronicle_id' => $this->chronicle->id,
            'created_by' => $this->storyteller->id,
        ]);
        $scene = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/game-sessions/'.$gameSession->id.'/scenes', ['title' => 'Сцена']);
        $sceneId = (int) $scene->json('scene.id');

        $player = User::factory()->create();

        $this->actingAs($player, 'sanctum')
            ->patchJson("/api/scenes/{$sceneId}/close")
            ->assertStatus(403)
            ->assertJsonPath('message', 'Только рассказчик.');
    }

    public function test_player_cannot_access_character_diary(): void
    {
        $npcId = $this->createNpc();
        $player = User::factory()->create();

        $this->actingAs($player, 'sanctum')
            ->getJson("/api/characters/{$npcId}/diary")
            ->assertStatus(403)
            ->assertJsonPath('message', 'Только рассказчик.');
    }

    private function createNpc(): int
    {
        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/characters', [
                'canonical_name' => 'NPC-дневник',
                'character_type' => 'npc',
            ]);
        $response->assertStatus(201);

        return (int) $response->json('character.id');
    }
}
