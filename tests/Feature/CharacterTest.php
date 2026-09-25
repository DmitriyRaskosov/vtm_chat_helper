<?php

namespace Tests\Feature;

use App\Models\CanonClan;
use App\Models\CanonSect;
use App\Models\Character;
use App\Models\Chronicle;
use App\Models\User;
use Database\Seeders\CanonClanSeeder;
use Database\Seeders\CanonSectSeeder;
use Tests\TestCase;

class CharacterTest extends TestCase
{
    private User $storyteller;
    private Chronicle $chronicle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CanonSectSeeder::class);
        $this->seed(CanonClanSeeder::class);

        $this->storyteller = User::factory()->storyteller()->create();
        $this->chronicle = Chronicle::query()->create([
            'title' => 'Тестовая хроника',
            'created_by' => $this->storyteller->id,
        ]);
    }

    public function test_storyteller_can_create_character(): void
    {
        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/characters', [
                'canonical_name' => 'Иван',
                'character_type' => 'npc',
                'chronicle_id' => $this->chronicle->id,
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
                'chronicle_id' => $this->chronicle->id,
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
                'chronicle_id' => $this->chronicle->id,
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
                'chronicle_id' => $this->chronicle->id,
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
                'chronicle_id' => $this->chronicle->id,
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
    }

    public function test_storyteller_can_list_characters(): void
    {
        foreach (['Иван', 'Пётр'] as $name) {
            $this->actingAs($this->storyteller, 'sanctum')
                ->postJson('/api/characters', [
                    'canonical_name' => $name,
                    'character_type' => 'npc',
                    'chronicle_id' => $this->chronicle->id,
                ])
                ->assertStatus(201);
        }

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->getJson('/api/characters?chronicle_id='.$this->chronicle->id);

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'characters');
    }
}