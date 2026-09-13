<?php

namespace Database\Factories;

use App\Models\CharacterAffiliation;
use App\Models\CharacterAffiliationChange;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CharacterAffiliationChange>
 */
class CharacterAffiliationChangeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'affiliation_id' => CharacterAffiliation::factory(),
            'character_id' => fn (array $attributes) => CharacterAffiliation::query()
                ->findOrFail($attributes['affiliation_id'])
                ->character_id,
            'field' => 'trust',
            'old_value' => ['v' => 0],
            'new_value' => ['v' => 2],
            'reason' => 'Test.',
            'scene_id' => null,
            'message_id' => null,
            'changed_by' => null,
            'revision' => 2,
        ];
    }
}
