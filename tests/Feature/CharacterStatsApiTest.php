<?php

namespace Tests\Feature;

use App\Enums\ChronicleRole;
use App\Models\Chronicle;
use App\Models\User;
use Tests\Support\JoinsChronicle;
use Tests\TestCase;

class CharacterStatsApiTest extends TestCase
{
    use JoinsChronicle;

    private User $storyteller;

    private Chronicle $chronicle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storyteller = User::factory()->create();
        $this->chronicle = Chronicle::factory()->create([
            'created_by' => $this->storyteller->id,
        ]);
        $this->joinChronicle($this->storyteller, $this->chronicle, ChronicleRole::Storyteller);
    }

    public function test_player_can_update_own_character_stats(): void
    {
        $player = User::factory()->create();
        $pcId = $this->createPlayerCharacter($player);
        $stats = $this->statsPayload();

        $this->actingAs($player, 'sanctum')
            ->patchJson("/api/characters/{$pcId}/stats", ['stats' => $stats])
            ->assertOk()
            ->assertJsonPath('stats.blood_pool.3', 1)
            ->assertJsonPath('stats.blood_pool.4', 0)
            ->assertJsonPath('stats.strength.1', 1)
            ->assertJsonPath('stats.strength.2', 2);

        $this->actingAs($player, 'sanctum')
            ->getJson("/api/characters/{$pcId}")
            ->assertOk()
            ->assertJsonPath('character.stats.blood_pool.3', 1)
            ->assertJsonPath('character.stats.strength.2', 2);
    }

    public function test_storyteller_can_update_character_stats(): void
    {
        $npcId = $this->createNpc();
        $stats = $this->statsPayload();

        $this->actingAs($this->storyteller, 'sanctum')
            ->patchJson("/api/characters/{$npcId}/stats", ['stats' => $stats])
            ->assertOk()
            ->assertJsonPath('stats.health.level', 2);

        $this->actingAs($this->storyteller, 'sanctum')
            ->getJson("/api/characters/{$npcId}")
            ->assertOk()
            ->assertJsonPath('character.stats.health.level', 2);
    }

    public function test_foreign_player_cannot_update_character_stats(): void
    {
        $owner = User::factory()->create();
        $foreign = User::factory()->create();
        $this->joinChronicle($foreign, $this->chronicle, ChronicleRole::Player);
        $pcId = $this->createPlayerCharacter($owner);

        $this->actingAs($foreign, 'sanctum')
            ->patchJson("/api/characters/{$pcId}/stats", ['stats' => $this->statsPayload()])
            ->assertForbidden();
    }

    /**
     * @return array<string, mixed>
     */
    private function statsPayload(): array
    {
        return [
            'blood_pool' => [1, 1, 1, 1, 0, 0, 0, 0, 0, 0],
            'strength' => [1, 1, 2, 2, 0],
            'health' => [
                'level' => 2,
                'damage' => 'bashing',
                'penalty' => -1,
            ],
        ];
    }

    private function createNpc(): int
    {
        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'characters'), [
                'canonical_name' => 'NPC статы',
                'character_type' => 'npc',
            ]);
        $response->assertStatus(201);

        return (int) $response->json('character.id');
    }

    private function createPlayerCharacter(User $player): int
    {
        $this->joinChronicle($player, $this->chronicle, ChronicleRole::Player);

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'characters'), [
                'canonical_name' => 'PC статы',
                'character_type' => 'player',
                'user_id' => $player->id,
            ]);
        $response->assertStatus(201);

        return (int) $response->json('character.id');
    }
}
