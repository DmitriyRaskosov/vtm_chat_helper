<?php

namespace Database\Factories;

use App\Enums\WorldEntityType;
use App\Models\WorldRelationType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorldRelationType>
 */
class WorldRelationTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'key' => 'custom_'.fake()->unique()->slug(2),
            'display_name' => 'Custom relation',
            'allowed_source_types' => [WorldEntityType::Character->value],
            'allowed_target_types' => [WorldEntityType::Concept->value],
            'symmetric' => false,
            'transitive' => false,
            'default_weight' => 1.0,
            'enabled' => true,
        ];
    }
}
