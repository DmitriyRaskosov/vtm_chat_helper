<?php

namespace Database\Factories;

use App\Enums\CharacterAffiliationStance;
use App\Enums\CharacterAffiliationType;
use App\Enums\WorldEntityType;
use App\Models\Character;
use App\Models\CharacterAffiliation;
use App\Models\WorldEntity;
use App\Models\WorldRelationType;
use App\World\WorldRelationService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CharacterAffiliation>
 */
class CharacterAffiliationFactory extends Factory
{
    public function definition(): array
    {
        $character = Character::factory()->create();
        $target = WorldEntity::factory()->create([
            'chronicle_id' => $character->chronicle_id,
            'entity_type' => WorldEntityType::Faction,
        ]);

        $edge = app(WorldRelationService::class)->relate(
            WorldEntity::query()->findOrFail($character->id),
            $target,
            WorldRelationType::query()->where('key', 'affiliated_with')->firstOrFail(),
        );

        return [
            'id' => $edge->id,
            'chronicle_id' => $character->chronicle_id,
            'character_id' => $character->id,
            'target_entity_id' => $target->id,
            'affiliation_type' => CharacterAffiliationType::Member,
            'stance' => CharacterAffiliationStance::Allied,
            'trust' => 0,
            'loyalty' => 0,
            'fear' => 0,
            'obligation' => 0,
            'role' => null,
            'rank' => null,
            'note' => null,
            'revision' => 1,
        ];
    }
}
