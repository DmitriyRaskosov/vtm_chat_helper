<?php

namespace Database\Factories;

use App\Enums\WorldEntityAliasType;
use App\Enums\WorldEntityStatus;
use App\Enums\WorldEntityType;
use App\Models\Chronicle;
use App\Models\WorldEntity;
use App\World\AliasNormalizer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorldEntity>
 */
class WorldEntityFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'chronicle_id' => Chronicle::factory(),
            'entity_type' => WorldEntityType::Location,
            'canonical_name' => $name,
            'slug' => AliasNormalizer::slug($name).'-'.fake()->unique()->numerify('###'),
            'short_description' => null,
            'status' => WorldEntityStatus::Active,
            'archived_at' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (WorldEntity $entity): void {
            if ($entity->aliases()->exists()) {
                return;
            }

            $entity->aliases()->create([
                'chronicle_id' => $entity->chronicle_id,
                'alias' => $entity->canonical_name,
                'normalized_alias' => AliasNormalizer::normalize($entity->canonical_name),
                'alias_type' => WorldEntityAliasType::Canonical,
                'language' => null,
            ]);
        });
    }

    public function archived(): static
    {
        return $this->state(fn () => [
            'status' => WorldEntityStatus::Archived,
            'archived_at' => now(),
        ]);
    }
}
