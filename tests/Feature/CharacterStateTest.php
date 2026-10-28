<?php

namespace Tests\Feature;

use App\Character\CharacterStateService;
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

    public function test_storyteller_can_update_any_character_state(): void
    {
        $npcId = $this->createNpc();

        $this->actingAs($this->storyteller, 'sanctum')
            ->putJson("/api/characters/{$npcId}/state", $this->statePayload(['blood_pool' => 5]))
            ->assertOk()
            ->assertJsonPath('state.blood_pool', 5);
    }

    public function test_player_can_update_own_character_state(): void
    {
        $player = User::factory()->create();
        $pcId = $this->createPlayerCharacter($player);

        $this->actingAs($player, 'sanctum')
            ->putJson("/api/characters/{$pcId}/state", $this->statePayload(['blood_pool' => 3]))
            ->assertOk()
            ->assertJsonPath('state.blood_pool', 3);
    }

    public function test_player_cannot_update_foreign_character_state(): void
    {
        $owner = User::factory()->create();
        $foreign = User::factory()->create();
        $this->joinChronicle($foreign, $this->chronicle, ChronicleRole::Player);

        $pcId = $this->createPlayerCharacter($owner);

        $this->actingAs($foreign, 'sanctum')
            ->putJson("/api/characters/{$pcId}/state", $this->statePayload())
            ->assertStatus(403);
    }

    public function test_player_cannot_update_npc_state(): void
    {
        $npcId = $this->createNpc();
        $player = User::factory()->create();
        $this->joinChronicle($player, $this->chronicle, ChronicleRole::Player);

        $this->actingAs($player, 'sanctum')
            ->putJson("/api/characters/{$npcId}/state", $this->statePayload())
            ->assertStatus(403);
    }

    public function test_default_state_has_10_blood_and_5_willpower(): void
    {
        $npcId = $this->createNpc();

        $this->actingAs($this->storyteller, 'sanctum')
            ->getJson("/api/characters/{$npcId}")
            ->assertOk()
            ->assertJsonPath('character.state.blood_pool', 10)
            ->assertJsonPath('character.state.temporary_willpower', 5)
            ->assertJsonPath('character.state.health_penalty', 0);
    }

    #[DataProvider('healthPenaltyLevelsProvider')]
    public function test_health_penalty_auto_calculated(int $healthLevel, int $expectedPenalty): void
    {
        $npcId = $this->createNpc();

        $this->actingAs($this->storyteller, 'sanctum')
            ->putJson("/api/characters/{$npcId}/state", $this->statePayload([
                'health_level' => $healthLevel,
            ]))
            ->assertOk()
            ->assertJsonPath('state.health_level', $healthLevel)
            ->assertJsonPath('state.health_penalty', $expectedPenalty);

        $this->assertSame($expectedPenalty, CharacterStateService::healthPenaltyForLevel($healthLevel));
    }

    public function test_update_returns_health_penalty(): void
    {
        $npcId = $this->createNpc();

        $this->actingAs($this->storyteller, 'sanctum')
            ->putJson("/api/characters/{$npcId}/state", $this->statePayload([
                'health_level' => 4,
            ]))
            ->assertOk()
            ->assertJsonPath('state.health_penalty', -2);
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

    /**
     * @param  array<string, int|string>  $overrides
     * @return array<string, int|string>
     */
    private function statePayload(array $overrides = []): array
    {
        return array_merge([
            'blood_pool' => 0,
            'temporary_willpower' => 0,
            'health_level' => 0,
            'health_damage' => 'bashing',
        ], $overrides);
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

    private function createPlayerCharacter(User $player): int
    {
        $this->joinChronicle($player, $this->chronicle, ChronicleRole::Player);

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'characters'), [
                'canonical_name' => 'PC состояние',
                'character_type' => 'player',
                'user_id' => $player->id,
            ]);
        $response->assertStatus(201);

        return (int) $response->json('character.id');
    }
}
