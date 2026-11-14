<?php

namespace Tests\Feature;

use App\Enums\ChronicleRole;
use App\Models\CanonClan;
use App\Models\Character;
use App\Models\Chronicle;
use App\Models\User;
use Database\Seeders\CanonClanSeeder;
use Database\Seeders\CanonSectSeeder;
use Tests\Support\JoinsChronicle;
use Tests\TestCase;

class CharacterCreationTest extends TestCase
{
    use JoinsChronicle;

    private User $storyteller;

    private User $player;

    private Chronicle $chronicle;

    private CanonClan $clan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CanonSectSeeder::class);
        $this->seed(CanonClanSeeder::class);

        $this->storyteller = User::factory()->create();
        $this->player = User::factory()->create();
        $this->chronicle = Chronicle::factory()->create([
            'title' => 'Тестовая хроника',
            'created_by' => $this->storyteller->id,
        ]);
        $this->joinChronicle($this->storyteller, $this->chronicle, ChronicleRole::Storyteller);
        $this->joinChronicle($this->player, $this->chronicle, ChronicleRole::Player);
        $this->clan = CanonClan::query()->where('slug', 'tremere')->firstOrFail();
    }

    public function test_player_auto_pc(): void
    {
        $created = $this->actingAs($this->player, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'characters'), [
                'canonical_name' => 'Аделина',
                'clan_id' => $this->clan->id,
                'concept' => 'Учёный Тремеров',
            ]);

        $created->assertStatus(201);
        $created->assertJsonPath('character.character_type', 'player');
        $created->assertJsonPath('character.user_id', $this->player->id);

        $other = User::factory()->create();

        $forged = $this->actingAs($this->player, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'characters'), [
                'canonical_name' => 'Подделка',
                'clan_id' => $this->clan->id,
                'character_type' => 'npc',
                'user_id' => $other->id,
            ]);

        $forged->assertStatus(201);
        $forged->assertJsonPath('character.character_type', 'player');
        $forged->assertJsonPath('character.user_id', $this->player->id);
        $this->assertDatabaseMissing('characters', [
            'chronicle_id' => $this->chronicle->id,
            'user_id' => $other->id,
        ]);
        $this->assertSame(2, Character::query()
            ->where('chronicle_id', $this->chronicle->id)
            ->where('character_type', 'player')
            ->where('user_id', $this->player->id)
            ->count());
    }

    public function test_storyteller_can_choose_npc(): void
    {
        $npc = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'characters'), [
                'canonical_name' => 'Горгулья',
                'character_type' => 'npc',
            ]);

        $npc->assertStatus(201);
        $npc->assertJsonPath('character.character_type', 'npc');
        $npc->assertJsonPath('character.user_id', null);

        $pc = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'characters'), [
                'canonical_name' => 'Подопечный',
                'character_type' => 'player',
                'user_id' => $this->player->id,
                'clan_id' => $this->clan->id,
            ]);

        $pc->assertStatus(201);
        $pc->assertJsonPath('character.character_type', 'player');
        $pc->assertJsonPath('character.user_id', $this->player->id);

        $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'characters'), [
                'canonical_name' => 'Без типа',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['character_type']);
    }
}
