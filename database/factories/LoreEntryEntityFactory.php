<?php

namespace Database\Factories;

use App\Models\LoreEntry;
use App\Models\LoreEntryEntity;
use App\Models\WorldEntity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoreEntryEntity>
 */
class LoreEntryEntityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'lore_entry_id' => LoreEntry::factory(),
            'entity_id' => fn (array $attributes) => WorldEntity::factory()->create([
                'chronicle_id' => LoreEntry::query()->findOrFail($attributes['lore_entry_id'])->chronicle_id,
            ])->id,
            'chronicle_id' => fn (array $attributes) => LoreEntry::query()
                ->findOrFail($attributes['lore_entry_id'])
                ->chronicle_id,
            'role' => 'subject',
        ];
    }
}
