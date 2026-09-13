<?php

namespace App\Models;

use App\Enums\CharacterBiographySection;
use Database\Factories\CharacterBioChunkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Pgvector\Laravel\HasNeighbors;
use Pgvector\Laravel\Vector;

#[Fillable([
    'biography_version_id',
    'character_id',
    'chunk_index',
    'section',
    'content',
    'token_estimate',
    'metadata',
    'embedding',
])]
class CharacterBioChunk extends Model
{
    /** @use HasFactory<CharacterBioChunkFactory> */
    use HasFactory;

    use HasNeighbors;

    /**
     * @return BelongsTo<CharacterBiographyVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(CharacterBiographyVersion::class, 'biography_version_id');
    }

    /**
     * @return BelongsTo<Character, $this>
     */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    protected function casts(): array
    {
        return [
            'biography_version_id' => 'integer',
            'character_id' => 'integer',
            'chunk_index' => 'integer',
            'section' => CharacterBiographySection::class,
            'token_estimate' => 'integer',
            'metadata' => 'array',
            'embedding' => Vector::class,
        ];
    }
}
