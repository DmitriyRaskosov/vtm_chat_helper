<?php

namespace Tests\Feature;

use App\Character\LibraryCharacterService;
use App\Enums\CharacterBiographyStatus;
use App\Enums\CharacterType;
use App\Enums\ChronicleRole;
use App\Models\CanonDiscipline;
use App\Models\CanonDisciplinePower;
use App\Models\Character;
use App\Models\CharacterBiography;
use App\Models\CharacterDiscipline;
use App\Models\CharacterItem;
use App\Models\CharacterPower;
use App\Models\Chronicle;
use App\Models\User;
use Database\Seeders\CanonDisciplineSeeder;
use Tests\Support\JoinsChronicle;
use Tests\TestCase;

class LibraryCharacterTest extends TestCase
{
    use JoinsChronicle;

    private User $owner;

    private User $storyteller;

    private Chronicle $chronicle;

    private LibraryCharacterService $library;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CanonDisciplineSeeder::class);

        $this->library = $this->app->make(LibraryCharacterService::class);

        $this->storyteller = User::factory()->create();
        $this->owner = User::factory()->create();

        $this->chronicle = Chronicle::factory()->create([
            'created_by' => $this->storyteller->id,
        ]);

        $this->joinChronicle($this->storyteller, $this->chronicle, ChronicleRole::Storyteller);
        $this->joinChronicle($this->owner, $this->chronicle, ChronicleRole::Player);
    }

    public function test_save_character_to_library(): void
    {
        $character = $this->createRichCharacter();

        $libraryCharacter = $this->library->createFromCharacter($this->owner, $character);

        $this->assertDatabaseHas('library_characters', [
            'id' => $libraryCharacter->id,
            'user_id' => $this->owner->id,
            'name' => 'Библиотечный герой',
            'character_type' => CharacterType::Player->value,
        ]);

        $this->assertDatabaseHas('library_character_biographies', [
            'library_character_id' => $libraryCharacter->id,
            'summary' => 'Кратко',
        ]);

        $this->assertDatabaseHas('library_character_items', [
            'library_character_id' => $libraryCharacter->id,
            'name' => 'Фляга',
            'quantity' => 2,
            'is_equipped' => true,
        ]);

        $celerityId = (int) CanonDiscipline::query()->where('slug', 'celerity')->value('id');

        $this->assertDatabaseHas('library_character_disciplines', [
            'library_character_id' => $libraryCharacter->id,
            'discipline_id' => $celerityId,
            'level' => 3,
        ]);

        $powerId = (int) CanonDisciplinePower::query()
            ->where('discipline_id', $celerityId)
            ->value('id');

        $this->assertDatabaseHas('library_character_powers', [
            'library_character_id' => $libraryCharacter->id,
            'discipline_power_id' => $powerId,
        ]);

        $libraryCharacter->refresh();

        $this->assertSame('Высокий вампир', $libraryCharacter->sheet_data['description'] ?? null);
        $this->assertSame(1, $libraryCharacter->stats['blood_pool'][2] ?? null);
        $this->assertSame(2, $libraryCharacter->stats['strength'][2] ?? null);
    }

    public function test_import_syncs_disciplines(): void
    {
        $character = $this->createRichCharacter();

        $libraryCharacter = $this->library->createFromCharacter($this->owner, $character);

        $targetChronicle = Chronicle::factory()->create([
            'created_by' => $this->storyteller->id,
        ]);
        $this->joinChronicle($this->storyteller, $targetChronicle, ChronicleRole::Storyteller);
        $this->joinChronicle($this->owner, $targetChronicle, ChronicleRole::Player);

        $libraryCharacter->refresh();
        $libraryCharacter->stats = [
            'blood_pool' => [1, 1, 0, 0, 0, 0, 0, 0, 0, 0],
        ];
        $libraryCharacter->save();

        $imported = $this->library->instantiateInChronicle(
            $libraryCharacter,
            $targetChronicle,
            $this->owner,
            'Библиотечный герой',
        );

        $celerityId = (int) CanonDiscipline::query()->where('slug', 'celerity')->value('id');

        $this->assertDatabaseHas('character_disciplines', [
            'character_id' => $imported->id,
            'discipline_id' => $celerityId,
            'level' => 3,
        ]);

        $imported->refresh();

        $this->assertSame(1, $imported->stats['disciplines']['celerity'][0] ?? null);
        $this->assertSame(1, $imported->stats['disciplines']['celerity'][1] ?? null);
        $this->assertSame(1, $imported->stats['disciplines']['celerity'][2] ?? null);
        $this->assertSame(0, $imported->stats['disciplines']['celerity'][3] ?? null);

        $this->assertDatabaseHas('character_items', [
            'character_id' => $imported->id,
            'name' => 'Фляга',
            'quantity' => 2,
        ]);

        $powerId = (int) CanonDisciplinePower::query()
            ->where('discipline_id', $celerityId)
            ->value('id');

        $this->assertDatabaseHas('character_powers', [
            'character_id' => $imported->id,
            'discipline_power_id' => $powerId,
        ]);
    }

    public function test_library_is_independent(): void
    {
        $character = $this->createRichCharacter();

        $libraryCharacter = $this->library->createFromCharacter($this->owner, $character);

        $character->stats = ['blood_pool' => array_fill(0, 10, 0)];
        $character->save();

        $character->items()->update(['quantity' => 99]);

        $libraryCharacter->refresh();

        $this->assertSame(2, $libraryCharacter->stats['strength'][2] ?? null);
        $this->assertSame(2, (int) $libraryCharacter->items()->value('quantity'));
    }

    private function createRichCharacter(): Character
    {
        $character = Character::factory()
            ->forChronicle($this->chronicle)
            ->player($this->owner)
            ->create(['canonical_name' => 'Библиотечный герой']);

        $character->stats = [
            'blood_pool' => [1, 1, 1, 0, 0, 0, 0, 0, 0, 0],
            'strength' => [1, 1, 2, 0, 0],
            'disciplines' => [
                'animalism' => [1, 0, 0, 0, 0],
            ],
        ];
        $character->sheet_data = [
            'description' => 'Высокий вампир',
            'traits' => [],
            'backgrounds' => [],
            'merits_flaws' => [],
        ];
        $character->save();

        CharacterBiography::query()->create([
            'character_id' => $character->id,
            'summary' => 'Кратко',
            'full_text' => 'Полный текст',
            'current_version' => 1,
            'status' => CharacterBiographyStatus::Approved,
        ]);

        CharacterItem::query()->create([
            'character_id' => $character->id,
            'name' => 'Фляга',
            'quantity' => 2,
            'is_equipped' => true,
        ]);

        $celerity = CanonDiscipline::query()->where('slug', 'celerity')->firstOrFail();

        CharacterDiscipline::query()->create([
            'character_id' => $character->id,
            'discipline_id' => $celerity->id,
            'level' => 3,
        ]);

        $power = CanonDisciplinePower::query()->create([
            'discipline_id' => $celerity->id,
            'level' => 1,
            'name' => 'Cat\'s Grace',
            'description' => 'Test power',
            'system' => 'Reflex +1',
            'source_book' => 'VtM20',
        ]);

        CharacterPower::query()->create([
            'character_id' => $character->id,
            'discipline_id' => $celerity->id,
            'discipline_power_id' => $power->id,
            'note' => 'Изучено',
        ]);

        return $character->refresh()->load(['entity', 'biography', 'items', 'disciplines', 'powers']);
    }
}
