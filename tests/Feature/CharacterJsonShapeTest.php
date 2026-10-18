<?php

namespace Tests\Feature;

use App\Enums\ChronicleRole;
use App\Models\Chronicle;
use App\Models\User;
use Database\Seeders\CanonClanSeeder;
use Database\Seeders\CanonSectSeeder;
use Tests\Support\JoinsChronicle;
use Tests\TestCase;

class CharacterJsonShapeTest extends TestCase
{
    use JoinsChronicle;

    private User $storyteller;

    private Chronicle $chronicle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CanonSectSeeder::class);
        $this->seed(CanonClanSeeder::class);

        $this->storyteller = User::factory()->create();
        $this->chronicle = Chronicle::factory()->create([
            'title' => 'Тестовая хроника',
            'created_by' => $this->storyteller->id,
        ]);
        $this->joinChronicle($this->storyteller, $this->chronicle, ChronicleRole::Storyteller);
    }

    public function test_get_characters_index_shape_for_storyteller(): void
    {
        $create = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'characters'), [
                'canonical_name' => 'Список-NPC',
                'character_type' => 'npc',
            ]);
        $create->assertStatus(201);
        $characterId = (int) $create->json('character.id');

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->getJson($this->chronicleApi($this->chronicle, 'characters'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'characters' => [
                '*' => ['id', 'canonical_name', 'character_type', 'user_id'],
            ],
            'archived',
        ]);

        $item = collect($response->json('characters'))->firstWhere('id', $characterId);
        $this->assertNotNull($item);
        $this->assertSame(['id', 'canonical_name', 'character_type', 'user_id'], array_keys($item));
        $this->assertIsInt($item['id']);
        $this->assertIsString($item['canonical_name']);
        $this->assertIsString($item['character_type']);
        $this->assertNull($item['user_id']);
    }

    public function test_get_characters_index_shape_for_player_without_character(): void
    {
        $player = User::factory()->create();
        $this->joinChronicle($player, $this->chronicle, ChronicleRole::Player);

        $response = $this->actingAs($player, 'sanctum')
            ->getJson($this->chronicleApi($this->chronicle, 'characters'));

        $response->assertStatus(200);
        $response->assertExactJson([
            'characters' => [],
            'archived' => [],
        ]);
    }

    public function test_get_character_show_shape(): void
    {
        $create = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'characters'), [
                'canonical_name' => 'Лист-NPC',
                'character_type' => 'npc',
            ]);
        $create->assertStatus(201);
        $characterId = (int) $create->json('character.id');

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->getJson("/api/characters/{$characterId}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'character' => [
                'id',
                'chronicle_id',
                'character_type',
                'canonical_name',
                'user_id',
                'clan_id',
                'clan',
                'sect_id',
                'sect',
                'sire_character_id',
                'sire_name',
                'generation',
                'nature',
                'demeanor',
                'concept',
                'is_active',
                'disciplines',
                'biography',
                'traits',
            ],
        ]);

        $character = $response->json('character');
        $this->assertSame(
            [
                'id',
                'chronicle_id',
                'character_type',
                'canonical_name',
                'user_id',
                'clan_id',
                'clan',
                'sect_id',
                'sect',
                'sire_character_id',
                'sire_name',
                'generation',
                'nature',
                'demeanor',
                'concept',
                'is_active',
                'disciplines',
                'biography',
                'traits',
            ],
            array_keys($character),
        );
        $this->assertIsInt($character['id']);
        $this->assertIsInt($character['chronicle_id']);
        $this->assertIsString($character['character_type']);
        $this->assertIsString($character['canonical_name']);
        $this->assertIsBool($character['is_active']);
        $this->assertIsArray($character['disciplines']);
        $this->assertIsArray($character['traits']);
    }

    public function test_post_characters_response_wraps_character_sheet(): void
    {
        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'characters'), [
                'canonical_name' => 'Созданный',
                'character_type' => 'npc',
            ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'character' => [
                'id',
                'canonical_name',
                'character_type',
                'disciplines',
                'traits',
            ],
        ]);
    }

    public function test_character_sheet_catalog_shape(): void
    {
        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->getJson('/api/character-sheet/catalog');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'disciplines',
            'clans',
            'bloodlines',
            'sects',
        ]);
    }
}
