<?php

namespace Database\Factories;

use App\Enums\WorldEntityAliasType;
use App\Models\WorldEntity;
use App\Models\WorldEntityAlias;
use App\World\AliasNormalizer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorldEntityAlias>
 */
class WorldEntityAliasFactory extends Factory
{
    public function definition(): array
    {
        $alias = fake()->unique()->words(2, true);

        return [
            'entity_id' => WorldEntity::factory(),
            'chronicle_id' => fn (array $attributes) => WorldEntity::query()
                ->findOrFail($attributes['entity_id'])
                ->chronicle_id,
            'alias' => $alias,
            'normalized_alias' => AliasNormalizer::normalize($alias),
            'alias_type' => WorldEntityAliasType::Aka,
            'language' => null,
        ];
    }
}
