<?php

namespace Tests\Feature;

use App\Character\DisciplineService;
use App\Models\Character;
use App\Models\CharacterDiscipline;
use App\Models\CharacterPower;
use App\Models\Discipline;
use App\Models\DisciplinePower;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CharacterDisciplineTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_and_learned_powers_are_relational_not_json_lists(): void
    {
        $character = Character::factory()->create();
        $service = $this->app->make(DisciplineService::class);
        $presence = $this->v20('presence');
        $awe = $service->addPower($presence, 'awe', 'Благоговение', 1, 'v20-presence-1');
        $service->setCharacterDiscipline($character, $presence, 2);
        $service->learnPower($character, $awe, parameters: ['dice' => 'Charisma+Performance']);

        $this->assertDatabaseHas('disciplines', [
            'ruleset' => 'v20',
            'key' => 'presence',
        ]);
        $this->assertDatabaseHas('discipline_powers', [
            'discipline_id' => $presence->id,
            'key' => 'awe',
            'required_level' => 1,
            'rule_key' => 'v20-presence-1',
        ]);
        $this->assertDatabaseHas('character_disciplines', [
            'character_id' => $character->id,
            'discipline_id' => $presence->id,
            'level' => 2,
        ]);
        $this->assertDatabaseHas('character_powers', [
            'character_id' => $character->id,
            'discipline_power_id' => $awe->id,
        ]);
        $this->assertSame(
            ['dice' => 'Charisma+Performance'],
            CharacterPower::query()->firstOrFail()->parameters,
        );
        $this->assertNull(CharacterDiscipline::query()->firstOrFail()->getAttribute('powers'));
    }

    public function test_sql_finds_characters_by_discipline_level_and_power(): void
    {
        $service = $this->app->make(DisciplineService::class);
        $presence = $this->v20('presence');
        $awe = $service->addPower($presence, 'awe', 'Благоговение', 1);
        $majesty = $service->addPower($presence, 'majesty', 'Величие', 5);

        $victoria = Character::factory()->create();
        $anna = Character::factory()->create();
        $service->setCharacterDiscipline($victoria, $presence, 3);
        $service->learnPower($victoria, $awe);
        $service->setCharacterDiscipline($anna, $presence, 1);

        $ids = Character::query()
            ->whereHas(
                'disciplines',
                fn ($query) => $query
                    ->where('discipline_id', $presence->id)
                    ->where('level', '>=', 3),
            )
            ->whereHas(
                'powers',
                fn ($query) => $query->where('discipline_power_id', $awe->id),
            )
            ->pluck('id')
            ->all();

        $this->assertSame([$victoria->id], $ids);
        $this->assertFalse(
            Character::query()
                ->whereHas('powers', fn ($query) => $query->where('discipline_power_id', $majesty->id))
                ->exists(),
        );
    }

    public function test_power_must_match_known_discipline_and_level(): void
    {
        $service = $this->app->make(DisciplineService::class);
        $presence = $this->v20('presence');
        $dominate = $this->v20('dominate');
        $awe = $service->addPower($presence, 'awe', 'Благоговение', 1);
        $command = $service->addPower($dominate, 'command', 'Приказ', 1);
        $majesty = $service->addPower($presence, 'majesty', 'Величие', 5);
        $character = Character::factory()->create();
        $service->setCharacterDiscipline($character, $presence, 2);

        try {
            $service->learnPower($character, $command);
            $this->fail('Expected an exception when the discipline is unknown.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame(
                'A character must know the discipline before learning a power.',
                $e->getMessage(),
            );
        }

        try {
            $service->learnPower($character, $majesty);
            $this->fail('Expected an exception when the level is too low.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame(
                'Character discipline level is below the power required level.',
                $e->getMessage(),
            );
        }

        $this->assertDatabaseCount('character_powers', 0);

        $this->expectException(QueryException::class);
        CharacterPower::query()->create([
            'character_id' => $character->id,
            'discipline_id' => $presence->id,
            'discipline_power_id' => $majesty->id,
        ]);
    }

    public function test_learned_power_is_unique_per_character(): void
    {
        $service = $this->app->make(DisciplineService::class);
        $presence = $this->v20('presence');
        $awe = $service->addPower($presence, 'awe', 'Благоговение', 1);
        $character = Character::factory()->create();
        $service->setCharacterDiscipline($character, $presence, 1);
        $service->learnPower($character, $awe);

        $this->expectException(QueryException::class);
        $service->learnPower($character, $awe);
    }

    public function test_discipline_level_cannot_drop_below_learned_powers(): void
    {
        $service = $this->app->make(DisciplineService::class);
        $presence = $this->v20('presence');
        $dread = $service->addPower($presence, 'dread-gaze', 'Ужасающий взор', 3);
        $character = Character::factory()->create();
        $service->setCharacterDiscipline($character, $presence, 3);
        $service->learnPower($character, $dread);

        $this->expectException(QueryException::class);
        $service->setCharacterDiscipline($character, $presence, 2);
    }

    public function test_factory_creates_compatible_learned_power(): void
    {
        $learned = CharacterPower::factory()->create();

        $this->assertTrue(Discipline::query()->whereKey($learned->discipline_id)->exists());
        $this->assertTrue(DisciplinePower::query()->whereKey($learned->discipline_power_id)->exists());
        $this->assertDatabaseHas('character_disciplines', [
            'character_id' => $learned->character_id,
            'discipline_id' => $learned->discipline_id,
        ]);
    }

    private function v20(string $key): Discipline
    {
        return Discipline::query()->where('ruleset', 'v20')->where('key', $key)->firstOrFail();
    }
}
