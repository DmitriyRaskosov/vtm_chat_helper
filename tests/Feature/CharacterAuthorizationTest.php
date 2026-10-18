<?php

namespace Tests\Feature;

use App\Enums\ChronicleRole;
use App\Models\Chronicle;
use App\Models\ChronicleMember;
use App\Models\GameSession;
use App\Models\User;
use Database\Seeders\CanonClanSeeder;
use Database\Seeders\CanonSectSeeder;
use Tests\Support\JoinsChronicle;
use Tests\TestCase;

class CharacterAuthorizationTest extends TestCase
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

    public function test_player_cannot_view_foreign_character_sheet(): void
    {
        $npcId = $this->createNpc('Чужой NPC');
        $player = User::factory()->create();

        $this->actingAs($player, 'sanctum')
            ->getJson("/api/characters/{$npcId}")
            ->assertStatus(403)
            ->assertJsonPath('message', 'You cannot access this character sheet.');
    }

    public function test_player_cannot_create_character(): void
    {
        $player = User::factory()->create();
        $this->joinChronicle($player, $this->chronicle, ChronicleRole::Player);

        $this->actingAs($player, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'characters'), [
                'canonical_name' => 'Запрещённый',
                'character_type' => 'npc',
            ])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Только рассказчик.');
    }

    public function test_player_cannot_speak_as_npc(): void
    {
        $gameSession = GameSession::factory()->active()->create([
            'chronicle_id' => $this->chronicle->id,
            'created_by' => $this->storyteller->id,
        ]);
        $scene = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/game-sessions/'.$gameSession->id.'/scenes', ['title' => 'Сцена']);
        $sceneId = (int) $scene->json('scene.id');
        $npcId = $this->createNpc('NPC');

        $player = User::factory()->create();
        $this->createPlayerCharacter($player, 'Герой');

        $this->actingAs($player, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'messages'), [
                'scene_id' => $sceneId,
                'character_id' => $npcId,
                'body' => 'От имени NPC',
            ])
            ->assertStatus(403);
    }

    private function createNpc(string $name): int
    {
        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'characters'), [
                'canonical_name' => $name,
                'character_type' => 'npc',
            ]);
        $response->assertStatus(201);

        return (int) $response->json('character.id');
    }

    private function createPlayerCharacter(User $player, string $name): int
    {
        ChronicleMember::factory()
            ->for($this->chronicle)
            ->for($player)
            ->player()
            ->create();

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
