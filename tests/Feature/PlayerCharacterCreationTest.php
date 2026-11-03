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

class PlayerCharacterCreationTest extends TestCase
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

    public function test_player_creates_one_character_and_second_returns_422(): void
    {
        $first = $this->actingAs($this->player, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'characters'), [
                'canonical_name' => 'Аделина',
                'clan_id' => $this->clan->id,
                'concept' => 'Учёный Тремеров',
            ]);

        $first->assertStatus(201);
        $first->assertJsonPath('character.character_type', 'player');
        $first->assertJsonPath('character.user_id', $this->player->id);
        $first->assertJsonPath('character.chronicle_id', $this->chronicle->id);
        $first->assertJsonPath('character.canonical_name', 'Аделина');
        $first->assertJsonPath('character.clan_id', $this->clan->id);
        $first->assertJsonPath('character.concept', 'Учёный Тремеров');

        $second = $this->actingAs($this->player, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'characters'), [
                'canonical_name' => 'Второй',
                'clan_id' => $this->clan->id,
            ]);

        $second->assertStatus(422);
        $second->assertJsonPath('message', 'У игрока уже есть активный персонаж в этой хронике.');
        $this->assertSame(1, Character::query()
            ->where('chronicle_id', $this->chronicle->id)
            ->where('user_id', $this->player->id)
            ->count());
    }

    public function test_player_cannot_create_npc_or_forge_user_id(): void
    {
        $other = User::factory()->create();

        $response = $this->actingAs($this->player, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'characters'), [
                'canonical_name' => 'Подделка',
                'clan_id' => $this->clan->id,
                'character_type' => 'npc',
                'user_id' => $other->id,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('character.character_type', 'player');
        $response->assertJsonPath('character.user_id', $this->player->id);
        $this->assertDatabaseMissing('characters', [
            'chronicle_id' => $this->chronicle->id,
            'user_id' => $other->id,
        ]);
        $this->assertDatabaseMissing('characters', [
            'chronicle_id' => $this->chronicle->id,
            'character_type' => 'npc',
            'user_id' => null,
        ]);
    }

    public function test_player_without_clan_returns_422(): void
    {
        $this->actingAs($this->player, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'characters'), [
                'canonical_name' => 'Без клана',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['clan_id']);
    }

    public function test_player_name_longer_than_100_returns_422(): void
    {
        $this->actingAs($this->player, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'characters'), [
                'canonical_name' => str_repeat('А', 101),
                'clan_id' => $this->clan->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['canonical_name']);
    }
}
