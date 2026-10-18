<?php

namespace Tests\Feature;

use App\Enums\ChronicleRole;
use App\Models\CanonClan;
use App\Models\CanonSect;
use App\Models\Character;
use App\Models\Chronicle;
use App\Models\User;
use Database\Seeders\CanonClanSeeder;
use Database\Seeders\CanonSectSeeder;
use Tests\Support\JoinsChronicle;
use Tests\TestCase;

class CharacterTest extends TestCase
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

    public function test_storyteller_can_create_character(): void
    {
        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/characters', [
                'canonical_name' => 'Иван',
                'character_type' => 'npc',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('character.canonical_name', 'Иван');
        $response->assertJsonPath('character.character_type', 'npc');

        $this->assertDatabaseHas('characters', [
            'id' => $response->json('character.id'),
            'character_type' => 'npc',
        ]);
    }

    public function test_player_cannot_create_character(): void
    {
        $player = User::factory()->create(); // role=Player по умолчанию

        $response = $this->actingAs($player, 'sanctum')
            ->postJson('/api/characters', [
                'canonical_name' => 'Запрещённый',
                'character_type' => 'npc',
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('world_entities', [
            'canonical_name' => 'Запрещённый',
        ]);
    }

    public function test_character_can_be_created_with_clan(): void
    {
        $clan = CanonClan::query()->where('slug', 'tremere')->firstOrFail();

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/characters', [
                'canonical_name' => 'Тремер-Иван',
                'character_type' => 'npc',
                'clan_id' => $clan->id,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('character.clan_id', $clan->id);
        $response->assertJsonPath('character.clan.slug', 'tremere');

        $this->assertDatabaseHas('characters', [
            'id' => $response->json('character.id'),
            'clan_id' => $clan->id,
        ]);
    }

    public function test_character_with_invalid_clan_returns_422(): void
    {
        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/characters', [
                'canonical_name' => 'Иван',
                'character_type' => 'npc',
                'clan_id' => 999_999,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['clan_id']);
    }

    public function test_storyteller_can_update_place(): void
    {
        $clan = CanonClan::query()->where('slug', 'tremere')->firstOrFail();
        $sect = CanonSect::query()->where('slug', 'camarilla')->firstOrFail();

        $create = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/characters', [
                'canonical_name' => 'Иван',
                'character_type' => 'npc',
            ]);
        $characterId = $create->json('character.id');

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->putJson("/api/characters/{$characterId}/place", [
                'clan_id' => $clan->id,
                'sect_id' => $sect->id,
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('character.clan_id', $clan->id);
        $response->assertJsonPath('character.sect_id', $sect->id);

        $this->assertDatabaseHas('characters', [
            'id' => $characterId,
            'clan_id' => $clan->id,
            'sect_id' => $sect->id,
        ]);

        $show = $this->actingAs($this->storyteller, 'sanctum')
            ->getJson("/api/characters/{$characterId}");
        $show->assertStatus(200);
        $characterPayload = $show->json('character');
        $this->assertArrayNotHasKey('haven_entity_id', $characterPayload);
        $this->assertArrayNotHasKey('haven_name', $characterPayload);

        $ignored = $this->actingAs($this->storyteller, 'sanctum')
            ->putJson("/api/characters/{$characterId}/place", [
                'clan_id' => $clan->id,
                'sect_id' => $sect->id,
                'haven_entity_id' => 1,
            ]);
        $ignored->assertStatus(200);
        $ignored->assertJsonMissingPath('character.haven_entity_id');
        $ignored->assertJsonMissingPath('character.haven_name');
    }

    public function test_storyteller_can_list_characters(): void
    {
        foreach (['Иван', 'Пётр'] as $name) {
            $this->actingAs($this->storyteller, 'sanctum')
                ->postJson('/api/characters', [
                    'canonical_name' => $name,
                    'character_type' => 'npc',
                ])
                ->assertStatus(201);
        }

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->getJson('/api/characters');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'characters');
    }

    public function test_duplicate_character_name_in_chronicle_returns_422(): void
    {
        $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/characters', [
                'canonical_name' => 'Иван',
                'character_type' => 'npc',
            ])
            ->assertStatus(201);

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/characters', [
                'canonical_name' => 'иван',
                'character_type' => 'npc',
            ]);

        $response->assertStatus(422);
        $response->assertSee('This name is already used in the chronicle.');
    }

    public function test_same_character_name_in_another_chronicle_succeeds(): void
    {
        $otherChronicle = Chronicle::factory()->create([
            'title' => 'Другая хроника',
            'created_by' => $this->storyteller->id,
        ]);

        $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/characters', [
                'canonical_name' => 'Иван',
                'character_type' => 'npc',
            ])
            ->assertStatus(201);

        Character::factory()
            ->forChronicle($otherChronicle)
            ->create(['canonical_name' => 'Иван']);

        $this->assertDatabaseCount('characters', 2);
    }

    public function test_character_rename_to_taken_name_returns_422(): void
    {
        $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/characters', [
                'canonical_name' => 'Первый',
                'character_type' => 'npc',
            ])
            ->assertStatus(201);

        $second = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/characters', [
                'canonical_name' => 'Второй',
                'character_type' => 'npc',
            ])
            ->assertStatus(201)
            ->json('character.id');

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->patchJson("/api/characters/{$second}", [
                'canonical_name' => 'Первый',
            ]);

        $response->assertStatus(422);
        $response->assertSee('This name is already used in the chronicle.');
    }

    public function test_character_rename_case_insensitive_conflict_returns_422(): void
    {
        $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/characters', [
                'canonical_name' => 'Первый',
                'character_type' => 'npc',
            ])
            ->assertStatus(201);

        $second = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/characters', [
                'canonical_name' => 'Второй',
                'character_type' => 'npc',
            ])
            ->assertStatus(201)
            ->json('character.id');

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->patchJson("/api/characters/{$second}", [
                'canonical_name' => 'первый',
            ]);

        $response->assertStatus(422);
        $response->assertSee('This name is already used in the chronicle.');
    }

    public function test_character_rename_to_free_name_succeeds(): void
    {
        $characterId = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/characters', [
                'canonical_name' => 'Старое имя',
                'character_type' => 'npc',
            ])
            ->assertStatus(201)
            ->json('character.id');

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->patchJson("/api/characters/{$characterId}", [
                'canonical_name' => 'Новое имя',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('character.canonical_name', 'Новое имя');
    }
}
