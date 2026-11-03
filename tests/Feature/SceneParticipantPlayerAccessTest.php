<?php

namespace Tests\Feature;

use App\Enums\ChronicleRole;
use App\Models\Chronicle;
use App\Models\GameSession;
use App\Models\User;
use Database\Seeders\CanonClanSeeder;
use Database\Seeders\CanonSectSeeder;
use Tests\Support\JoinsChronicle;
use Tests\TestCase;

class SceneParticipantPlayerAccessTest extends TestCase
{
    use JoinsChronicle;

    private User $storyteller;

    private Chronicle $chronicle;

    private GameSession $gameSession;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CanonSectSeeder::class);
        $this->seed(CanonClanSeeder::class);

        $this->storyteller = User::factory()->create();
        $this->chronicle = Chronicle::factory()->create([
            'created_by' => $this->storyteller->id,
        ]);
        $this->joinChronicle($this->storyteller, $this->chronicle, ChronicleRole::Storyteller);
        $this->gameSession = GameSession::factory()->active()->create([
            'chronicle_id' => $this->chronicle->id,
            'created_by' => $this->storyteller->id,
        ]);
    }

    public function test_player_can_add_own_pc_to_scene(): void
    {
        $player = User::factory()->create();
        $this->joinChronicle($player, $this->chronicle, ChronicleRole::Player);
        $pcId = $this->createPlayerCharacter($player, 'Мой герой');
        $sceneId = $this->createScene();

        $response = $this->actingAs($player, 'sanctum')
            ->postJson("/api/scenes/{$sceneId}/participants", [
                'character_id' => $pcId,
                'role' => 'player',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('scene_participants', [
            'scene_id' => $sceneId,
            'character_id' => $pcId,
            'is_current' => true,
        ]);
    }

    public function test_player_cannot_add_foreign_pc_to_scene(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $this->joinChronicle($owner, $this->chronicle, ChronicleRole::Player);
        $this->joinChronicle($intruder, $this->chronicle, ChronicleRole::Player);
        $foreignPcId = $this->createPlayerCharacter($owner, 'Чужой герой');
        $sceneId = $this->createScene();

        $response = $this->actingAs($intruder, 'sanctum')
            ->postJson("/api/scenes/{$sceneId}/participants", [
                'character_id' => $foreignPcId,
                'role' => 'player',
            ]);

        $response->assertStatus(403);
    }

    public function test_player_can_leave_own_pc_from_scene(): void
    {
        $player = User::factory()->create();
        $this->joinChronicle($player, $this->chronicle, ChronicleRole::Player);
        $pcId = $this->createPlayerCharacter($player, 'На сцене');
        $sceneId = $this->createScene();

        $this->actingAs($player, 'sanctum')
            ->postJson("/api/scenes/{$sceneId}/participants", [
                'character_id' => $pcId,
                'role' => 'player',
            ])
            ->assertStatus(201);

        $response = $this->actingAs($player, 'sanctum')
            ->patchJson("/api/scenes/{$sceneId}/participants/{$pcId}");

        $response->assertStatus(200);
        $this->assertDatabaseHas('scene_participants', [
            'scene_id' => $sceneId,
            'character_id' => $pcId,
            'is_current' => false,
        ]);
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

    private function createPlayerCharacter(User $player, string $name): int
    {
        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'characters'), [
                'canonical_name' => $name,
                'character_type' => 'player',
                'user_id' => $player->id,
            ]);
        $response->assertStatus(201);

        return (int) $response->json('character.id');
    }
}
