<?php

namespace Database\Factories;

use App\Enums\LoreEntryKind;
use App\Enums\LoreEntryStatus;
use App\Enums\LoreVisibility;
use App\Models\Chronicle;
use App\Models\LoreEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoreEntry>
 */
class LoreEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'chronicle_id' => Chronicle::factory(),
            'title' => 'Маскарад',
            'kind' => LoreEntryKind::Custom,
            'canonical_text' => 'Каиниты не раскрывают свою природу смертным.',
            'status' => LoreEntryStatus::Draft,
            'visibility' => LoreVisibility::Public,
            'current_version' => 1,
            'legacy_source_id' => null,
            'created_by' => null,
            'approved_at' => null,
            'approved_by' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => LoreEntryStatus::Approved,
            'approved_at' => now(),
        ]);
    }
}
