<?php

namespace Database\Factories;

use App\Enums\LoreEntryKind;
use App\Enums\LoreEntryStatus;
use App\Enums\LoreVisibility;
use App\Models\LoreEntry;
use App\Models\LoreEntryVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoreEntryVersion>
 */
class LoreEntryVersionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'lore_entry_id' => LoreEntry::factory(),
            'chronicle_id' => fn (array $attributes) => LoreEntry::query()
                ->findOrFail($attributes['lore_entry_id'])
                ->chronicle_id,
            'version' => 1,
            'title' => 'Маскарад',
            'kind' => LoreEntryKind::Custom,
            'canonical_text' => 'Каиниты не раскрывают свою природу смертным.',
            'status' => LoreEntryStatus::Draft,
            'visibility' => LoreVisibility::Public,
            'change_reason' => 'Первая версия.',
            'created_by' => null,
        ];
    }
}
