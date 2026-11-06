<?php

namespace Tests\Feature;

use App\Enums\ChronicleRole;
use App\Models\CanonClan;
use App\Models\Chronicle;
use App\Models\User;
use Database\Seeders\CanonClanDisciplineSeeder;
use Database\Seeders\CanonClanSeeder;
use Database\Seeders\CanonDisciplineSeeder;
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

        $this->seed(CanonClanSeeder::class);
        $this->seed(CanonDisciplineSeeder::class);
        $this->seed(CanonClanDisciplineSeeder::class);

        $this->storyteller = User::factory()->create();
        $this->chronicle = Chronicle::factory()->create([
            'created_by' => $this->storyteller->id,
        ]);
        $this->joinChronicle($this->storyteller, $this->chronicle, ChronicleRole::Storyteller);
    }

    public function test_sheet_marks_clan_disciplines_and_groups_schema(): void
    {
        $brujah = CanonClan::query()->where('slug', 'brujah')->firstOrFail();
        $characterId = $this->createNpc($brujah->id);

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->getJson("/api/characters/{$characterId}")
            ->assertOk();

        $disciplines = collect($response->json('character.available_disciplines'));
        $celerity = $disciplines->firstWhere('slug', 'celerity');
        $animalism = $disciplines->firstWhere('slug', 'animalism');

        $this->assertNotNull($celerity);
        $this->assertNotNull($animalism);
        $this->assertTrue($celerity['is_clan_discipline']);
        $this->assertFalse($animalism['is_clan_discipline']);
        $this->assertSame('Сила', $response->json('character.sheet_schema.physical.strength'));
        $this->assertSame('Бдительность', $response->json('character.sheet_schema.talents.alertness'));
        $this->assertSame('Совесть', $response->json('character.sheet_schema.virtues.conscience'));
        $this->assertSame('Человечность', $response->json('character.sheet_schema.state.humanity'));
    }

    public function test_player_can_save_sheet_data_and_grouped_stats(): void
    {
        $player = User::factory()->create();
        $pcId = $this->createPlayerCharacter($player);

        $this->actingAs($player, 'sanctum')
            ->patchJson("/api/characters/{$pcId}/stats", [
                'stats' => [
                    'humanity' => [1, 1, 1, 1, 1, 1, 1, 0, 0, 0],
                    'conscience' => [1, 1, 1, 0, 0],
                    'willpower' => [1, 1, 1, 1, 1, 0, 0, 0, 0, 0],
                    'temporary_willpower' => [1, 1, 1, 0, 0, 0, 0, 0, 0, 0],
                    'disciplines' => [
                        'celerity' => [1, 1, 1, 0, 0],
                    ],
                    'health' => [
                        'level' => 1,
                        'damage' => 'bashing',
                        'penalty' => 0,
                    ],
                ],
                'sheet_data' => [
                    'backgrounds' => [
                        ['name' => 'Союзники', 'value' => 2],
                    ],
                    'merits_flaws' => [
                        ['name' => 'Здравый смысл', 'points' => 1, 'type' => 'merit'],
                    ],
                    'weakness' => 'Вспыльчивость',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('stats.humanity.6', 1)
            ->assertJsonPath('stats.humanity.7', 0)
            ->assertJsonPath('stats.conscience.2', 1)
            ->assertJsonPath('stats.temporary_willpower.2', 1)
            ->assertJsonPath('stats.disciplines.celerity.2', 1)
            ->assertJsonPath('sheet_data.backgrounds.0.name', 'Союзники')
            ->assertJsonPath('sheet_data.backgrounds.0.value', 2)
            ->assertJsonPath('sheet_data.merits_flaws.0.type', 'merit')
            ->assertJsonPath('sheet_data.weakness', 'Вспыльчивость');

        $this->actingAs($player, 'sanctum')
            ->getJson("/api/characters/{$pcId}")
            ->assertOk()
            ->assertJsonPath('character.stats.humanity.6', 1)
            ->assertJsonPath('character.stats.disciplines.celerity.2', 1)
            ->assertJsonPath('character.sheet_data.weakness', 'Вспыльчивость');

        $this->actingAs($player, 'sanctum')
            ->patchJson("/api/characters/{$pcId}/stats", [
                'stats' => [
                    'blood_pool' => [1, 1, 1, 1, 0, 0, 0, 0, 0, 0],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('stats.blood_pool.3', 1)
            ->assertJsonPath('sheet_data.weakness', 'Вспыльчивость');
    }

    public function test_invalid_sheet_data_is_rejected(): void
    {
        $npcId = $this->createNpc();

        $this->actingAs($this->storyteller, 'sanctum')
            ->patchJson("/api/characters/{$npcId}/stats", [
                'stats' => [
                    'blood_pool' => [1, 0, 0, 0, 0, 0, 0, 0, 0, 0],
                ],
                'sheet_data' => [
                    'merits_flaws' => [
                        ['name' => 'Странность', 'points' => 1, 'type' => 'unknown'],
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sheet_data.merits_flaws.0.type']);
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

    private function createNpc(?int $clanId = null): int
    {
        $payload = [
            'canonical_name' => 'NPC статы',
            'character_type' => 'npc',
        ];
        if ($clanId !== null) {
            $payload['clan_id'] = $clanId;
        }

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'characters'), $payload);
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
