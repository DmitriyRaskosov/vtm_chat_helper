<?php

namespace Tests\Feature;

use App\Enums\CharacterType;
use App\Enums\ChronicleRole;
use App\Models\CanonClan;
use App\Models\Character;
use App\Models\Chronicle;
use App\Models\LibraryCharacter;
use App\Models\User;
use Database\Seeders\CanonClanSeeder;
use Database\Seeders\CanonDisciplineSeeder;
use Database\Seeders\CanonSectSeeder;
use Tests\Support\JoinsChronicle;
use Tests\TestCase;

class LibraryCharacterApiTest extends TestCase
{
    use JoinsChronicle;

    private User $owner;

    private User $intruder;

    private User $storyteller;

    private Chronicle $chronicle;

    private Character $playerCharacter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CanonDisciplineSeeder::class);

        $this->storyteller = User::factory()->create();
        $this->owner = User::factory()->create();
        $this->intruder = User::factory()->create();

        $this->chronicle = Chronicle::factory()->create([
            'created_by' => $this->storyteller->id,
        ]);

        $this->joinChronicle($this->storyteller, $this->chronicle, ChronicleRole::Storyteller);
        $this->joinChronicle($this->owner, $this->chronicle, ChronicleRole::Player);

        $this->playerCharacter = Character::factory()
            ->forChronicle($this->chronicle)
            ->player($this->owner)
            ->create(['canonical_name' => 'API Герой']);
    }

    public function test_store_saves_character_to_library(): void
    {
        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/library-characters', [
                'character_id' => $this->playerCharacter->id,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('library_character.name', 'API Герой');
        $response->assertJsonPath('library_character.character_type', CharacterType::Player->value);

        $libraryId = (int) $response->json('library_character.id');

        $this->assertDatabaseHas('library_characters', [
            'id' => $libraryId,
            'user_id' => $this->owner->id,
            'name' => 'API Герой',
        ]);
    }

    public function test_import_creates_character_in_chronicle(): void
    {
        $store = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/library-characters', [
                'character_id' => $this->playerCharacter->id,
            ])
            ->assertCreated();

        $libraryId = (int) $store->json('library_character.id');

        $targetChronicle = Chronicle::factory()->create([
            'created_by' => $this->storyteller->id,
        ]);
        $this->joinChronicle($this->storyteller, $targetChronicle, ChronicleRole::Storyteller);
        $this->joinChronicle($this->owner, $targetChronicle, ChronicleRole::Player);

        $import = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/chronicles/'.$targetChronicle->id.'/characters/import', [
                'library_character_id' => $libraryId,
                'canonical_name' => 'API Герой',
            ]);

        $import->assertStatus(201);
        $import->assertJsonPath('character.canonical_name', 'API Герой');
        $import->assertJsonPath('character.character_type', CharacterType::Player->value);

        $characterId = (int) $import->json('character.id');

        $this->assertDatabaseHas('characters', [
            'id' => $characterId,
            'chronicle_id' => $targetChronicle->id,
            'user_id' => $this->owner->id,
        ]);
    }

    public function test_import_with_custom_name_when_archived_character_uses_template_name(): void
    {
        $store = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/library-characters', [
                'character_id' => $this->playerCharacter->id,
            ])
            ->assertCreated();

        $libraryId = (int) $store->json('library_character.id');

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/characters/'.$this->playerCharacter->id.'/archive')
            ->assertOk();

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/chronicles/'.$this->chronicle->id.'/characters/import', [
                'library_character_id' => $libraryId,
                'canonical_name' => 'API Герой',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Это имя уже занято в хронике, выберите другое');

        $import = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/chronicles/'.$this->chronicle->id.'/characters/import', [
                'library_character_id' => $libraryId,
                'canonical_name' => 'API Герой (копия)',
            ]);

        $import->assertCreated();
        $import->assertJsonPath('character.canonical_name', 'API Герой (копия)');
    }

    public function test_player_creates_library_template_as_player(): void
    {
        $this->seed(CanonSectSeeder::class);
        $this->seed(CanonClanSeeder::class);
        $clan = CanonClan::query()->where('slug', 'tremere')->firstOrFail();

        $response = $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/library-characters', [
                'name' => 'Шаблон Аделины',
                'clan_id' => $clan->id,
                'concept' => 'Учёный',
                'character_type' => 'npc',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('library_character.name', 'Шаблон Аделины');
        $response->assertJsonPath('library_character.character_type', CharacterType::Player->value);
        $response->assertJsonPath('library_character.clan_id', $clan->id);
        $response->assertJsonPath('library_character.concept', 'Учёный');

        $this->assertDatabaseHas('library_characters', [
            'user_id' => $this->owner->id,
            'name' => 'Шаблон Аделины',
            'character_type' => CharacterType::Player->value,
        ]);
        $this->assertDatabaseMissing('world_entities', [
            'canonical_name' => 'Шаблон Аделины',
        ]);
    }

    public function test_player_library_template_without_clan_returns_422(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/library-characters', [
                'name' => 'Без клана',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['clan_id']);
    }

    public function test_storyteller_can_create_npc_library_template(): void
    {
        $created = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/library-characters', [
                'name' => 'Горгулья',
                'character_type' => 'npc',
            ]);

        $created->assertCreated();
        $created->assertJsonPath('library_character.character_type', CharacterType::Npc->value);
        $created->assertJsonPath('library_character.name', 'Горгулья');

        $this->assertDatabaseHas('library_characters', [
            'user_id' => $this->storyteller->id,
            'name' => 'Горгулья',
            'character_type' => CharacterType::Npc->value,
        ]);

        $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/library-characters', [
                'name' => 'Без типа',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['character_type']);
    }

    public function test_user_cannot_delete_others_library_template(): void
    {
        $libraryCharacter = LibraryCharacter::query()->create([
            'user_id' => $this->owner->id,
            'name' => 'Чужой шаблон',
            'character_type' => CharacterType::Player,
        ]);

        $this->actingAs($this->intruder, 'sanctum')
            ->deleteJson('/api/library-characters/'.$libraryCharacter->id)
            ->assertForbidden();

        $this->assertDatabaseHas('library_characters', ['id' => $libraryCharacter->id]);
    }
}
