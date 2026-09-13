<?php

namespace App\Models;

use Database\Factories\CharacterRelationshipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'id',
    'chronicle_id',
    'source_character_id',
    'target_character_id',
])]
class CharacterRelationship extends Model
{
    /** @use HasFactory<CharacterRelationshipFactory> */
    use HasFactory;

    public $incrementing = false;

    /**
     * Shared-PK world_relations row is read from WorldRelation::characterRelationship().
     * Inverse belongsTo on the same `id` is omitted: Eloquent recurses until PHP dies.
     *
     * @return BelongsTo<Character, $this>
     */
    public function sourceCharacter(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'source_character_id');
    }

    /**
     * @return BelongsTo<Character, $this>
     */
    public function targetCharacter(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'target_character_id');
    }

    /**
     * @return BelongsTo<Chronicle, $this>
     */
    public function chronicle(): BelongsTo
    {
        return $this->belongsTo(Chronicle::class);
    }

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'chronicle_id' => 'integer',
            'source_character_id' => 'integer',
            'target_character_id' => 'integer',
        ];
    }
}
