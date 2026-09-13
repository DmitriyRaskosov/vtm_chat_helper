<?php

namespace Tests\Feature;

use App\Character\CharacterSheetReader;
use App\Character\CharacterStatService;
use App\Enums\CharacterStatCategory;
use App\Models\Character;
use App\Models\CharacterStat;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CharacterStatTest extends TestCase
{
    use RefreshDatabase;

    public function test_stats_are_stored_as_rows_not_a_json_sheet(): void
    {
        $character = Character::factory()->create();
        $service = $this->app->make(CharacterStatService::class);

        $service->putStat($character, CharacterStatCategory::Attribute, 'strength', 'Сила', 3, 5, 1);
        $service->putStat($character, CharacterStatCategory::Ability, 'brawl', 'Драка', 2, 5, 10);
        $service->addSpecialization(
            $character->stats()->where('stat_key', 'brawl')->firstOrFail(),
            'Клинки',
            'Ножи и мечи.',
        );

        $this->assertDatabaseCount('character_stats', 2);
        $this->assertDatabaseHas('character_stats', [
            'character_id' => $character->id,
            'category' => CharacterStatCategory::Attribute->value,
            'stat_key' => 'strength',
            'value' => 3,
            'maximum' => 5,
        ]);
        $this->assertDatabaseHas('character_stat_specializations', [
            'name' => 'Клинки',
            'is_active' => true,
        ]);
        $this->assertNull(CharacterStat::query()->where('stat_key', 'strength')->value('metadata'));
    }

    public function test_stat_key_is_unique_per_character_and_category(): void
    {
        $character = Character::factory()->create();
        CharacterStat::factory()->create([
            'character_id' => $character->id,
            'category' => CharacterStatCategory::Attribute,
            'stat_key' => 'strength',
        ]);

        $this->expectException(QueryException::class);

        CharacterStat::factory()->create([
            'character_id' => $character->id,
            'category' => CharacterStatCategory::Attribute,
            'stat_key' => 'strength',
        ]);
    }

    public function test_same_stat_key_can_exist_in_another_category_or_character(): void
    {
        $first = Character::factory()->create();
        $second = Character::factory()->create();
        $service = $this->app->make(CharacterStatService::class);

        $service->putStat($first, CharacterStatCategory::Attribute, 'strength', 'Сила', 3);
        $service->putStat($first, CharacterStatCategory::Other, 'strength', 'Сила воли', 5);
        $service->putStat($second, CharacterStatCategory::Attribute, 'strength', 'Сила', 1);

        $this->assertDatabaseCount('character_stats', 3);
    }

    public function test_sql_finds_stats_by_category_and_value(): void
    {
        $character = Character::factory()->create();
        $service = $this->app->make(CharacterStatService::class);
        $service->putStat($character, CharacterStatCategory::Ability, 'brawl', 'Драка', 4, 5);
        $service->putStat($character, CharacterStatCategory::Ability, 'etiquette', 'Этикет', 1, 5);
        $service->putStat($character, CharacterStatCategory::Attribute, 'strength', 'Сила', 3, 5);

        $keys = CharacterStat::query()
            ->where('character_id', $character->id)
            ->where('category', CharacterStatCategory::Ability->value)
            ->where('value', '>=', 3)
            ->orderBy('stat_key')
            ->pluck('stat_key')
            ->all();

        $this->assertSame(['brawl'], $keys);
    }

    public function test_read_model_returns_full_sheet_and_relevant_subset(): void
    {
        $character = Character::factory()->create();
        $service = $this->app->make(CharacterStatService::class);
        $reader = $this->app->make(CharacterSheetReader::class);

        $service->putStat($character, CharacterStatCategory::Attribute, 'strength', 'Сила', 3, 5, 1);
        $brawl = $service->putStat($character, CharacterStatCategory::Ability, 'brawl', 'Драка', 4, 5, 10);
        $service->putStat($character, CharacterStatCategory::Ability, 'etiquette', 'Этикет', 1, 5, 11);
        $service->addSpecialization($brawl, 'Клинки');

        $full = $reader->full($character);
        $this->assertTrue($full->forCharacter($character));
        $this->assertCount(3, $full->stats);
        $this->assertSame(['strength', 'brawl', 'etiquette'], $full->stats->pluck('stat_key')->all());
        $this->assertSame(['Клинки'], $full->stats[1]->specializations->pluck('name')->all());

        $relevant = $reader->relevant(
            $character,
            categories: [CharacterStatCategory::Ability],
            minValue: 3,
        );
        $this->assertSame(['brawl'], $relevant->stats->pluck('stat_key')->all());

        $byKey = $reader->relevant($character, statKeys: ['strength', 'etiquette']);
        $this->assertSame(['strength', 'etiquette'], $byKey->stats->pluck('stat_key')->all());
    }

    public function test_value_cannot_exceed_maximum(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->app->make(CharacterStatService::class)->putStat(
            Character::factory()->create(),
            CharacterStatCategory::Attribute,
            'strength',
            'Сила',
            6,
            5,
        );
    }

    public function test_put_stat_updates_existing_row(): void
    {
        $character = Character::factory()->create();
        $service = $this->app->make(CharacterStatService::class);
        $service->putStat($character, CharacterStatCategory::Attribute, 'strength', 'Сила', 2, 5);
        $service->putStat($character, CharacterStatCategory::Attribute, 'strength', 'Сила', 4, 5);

        $this->assertDatabaseCount('character_stats', 1);
        $this->assertDatabaseHas('character_stats', [
            'character_id' => $character->id,
            'stat_key' => 'strength',
            'value' => 4,
        ]);
    }
}
