<?php

namespace Database\Factories;

use App\Enums\CharacterBiographySection;
use App\Models\Character;
use App\Models\CharacterBioChunk;
use App\Models\CharacterBiographyVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CharacterBioChunk>
 */
class CharacterBioChunkFactory extends Factory
{
    public function definition(): array
    {
        $dim = (int) config('rag.dimensions', 1024);
        $embedding = array_fill(0, $dim, 0.0);
        $embedding[0] = 1.0;

        return [
            'biography_version_id' => CharacterBiographyVersion::factory(),
            'character_id' => fn (array $attributes) => CharacterBiographyVersion::query()
                ->findOrFail($attributes['biography_version_id'])
                ->character_id,
            'chunk_index' => 0,
            'section' => CharacterBiographySection::Summary,
            'content' => 'A fledgling of the Camarilla.',
            'token_estimate' => 10,
            'metadata' => ['estimator' => 'test'],
            'embedding' => $embedding,
        ];
    }

    public function forCharacter(Character $character): static
    {
        return $this->state(fn () => [
            'character_id' => $character->id,
        ]);
    }
}
