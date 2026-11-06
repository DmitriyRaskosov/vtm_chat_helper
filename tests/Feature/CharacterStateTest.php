<?php

namespace Tests\Feature;

use App\Character\CharacterStatsService;
use App\Enums\ChronicleRole;
use App\Models\Chronicle;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\JoinsChronicle;
use Tests\TestCase;

class CharacterStateTest extends TestCase
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

    public function test_legacy_state_endpoint_is_removed(): void
    {
        $npcId = $this->createNpc();

        $this->actingAs($this->storyteller, 'sanctum')
            ->putJson("/api/characters/{$npcId}/state", [
                'blood_pool' => 5,
                'temporary_willpower' => 1,
                'health_level' => 0,
                'health_damage' => 'bashing',
            ])
            ->assertNotFound();
    }

    public function test_new_character_does_not_create_character_state_row(): void
    {
        $npcId = $this->createNpc();

        $this->assertDatabaseMissing('character_states', [
            'character_id' => $npcId,
        ]);
    }

    #[DataProvider('healthPenaltyLevelsProvider')]
    public function test_health_penalty_comes_from_stats(int $healthLevel, int $expectedPenalty): void
    {
        $this->assertSame($expectedPenalty, CharacterStatsService::healthPenalty($healthLevel));
    }

    /**
     * @return array<string, array{0: int, 1: int}>
     */
    public static function healthPenaltyLevelsProvider(): array
    {
        return [
            'healthy' => [0, 0],
            'bruised' => [1, 0],
            'hurt' => [2, -1],
            'injured' => [3, -1],
            'wounded' => [4, -2],
            'mauled' => [5, -2],
            'crippled' => [6, -5],
            'incapacitated' => [7, -100],
        ];
    }

    private function createNpc(): int
    {
        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'characters'), [
                'canonical_name' => 'NPC состояние',
                'character_type' => 'npc',
            ]);
        $response->assertStatus(201);

        return (int) $response->json('character.id');
    }
}
