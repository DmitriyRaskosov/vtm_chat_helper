<?php

namespace Database\Factories;

use App\Enums\LoreChunkSection;
use App\Enums\LoreVisibility;
use App\Models\LoreChunk;
use App\Models\LoreEntryVersion;
use App\Rag\StubEmbeddingProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoreChunk>
 */
class LoreChunkFactory extends Factory
{
    public function definition(): array
    {
        $content = 'Каиниты не раскрывают свою природу смертным.';

        return [
            'lore_entry_version_id' => LoreEntryVersion::factory(),
            'lore_entry_id' => fn (array $attributes) => LoreEntryVersion::query()
                ->findOrFail($attributes['lore_entry_version_id'])
                ->lore_entry_id,
            'chronicle_id' => fn (array $attributes) => LoreEntryVersion::query()
                ->findOrFail($attributes['lore_entry_version_id'])
                ->chronicle_id,
            'chunk_index' => 0,
            'section' => LoreChunkSection::CanonicalText,
            'content' => $content,
            'token_estimate' => 12,
            'visibility' => LoreVisibility::Public,
            'metadata' => null,
            'embedding' => (new StubEmbeddingProvider)->embed($content),
        ];
    }
}
