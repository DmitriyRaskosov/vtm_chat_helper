<?php

namespace App\Models;

use App\Enums\CharacterAffiliationStance;
use App\Enums\CharacterAffiliationType;
use Database\Factories\CharacterAffiliationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'id',
    'chronicle_id',
    'character_id',
    'target_entity_id',
    'affiliation_type',
    'stance',
    'trust',
    'loyalty',
    'fear',
    'obligation',
    'role',
    'rank',
    'note',
    'revision',
])]
class CharacterAffiliation extends Model
{
    /** @use HasFactory<CharacterAffiliationFactory> */
    use HasFactory;

    public $incrementing = false;

    /**
     * Shared-PK world_relations row is read from WorldRelation::characterAffiliation().
     *
     * @return BelongsTo<Character, $this>
     */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    /**
     * @return BelongsTo<WorldEntity, $this>
     */
    public function target(): BelongsTo
    {
        return $this->belongsTo(WorldEntity::class, 'target_entity_id');
    }

    /**
     * @return HasMany<CharacterAffiliationChange, $this>
     */
    public function changes(): HasMany
    {
        return $this->hasMany(CharacterAffiliationChange::class, 'affiliation_id');
    }

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'chronicle_id' => 'integer',
            'character_id' => 'integer',
            'target_entity_id' => 'integer',
            'affiliation_type' => CharacterAffiliationType::class,
            'stance' => CharacterAffiliationStance::class,
            'trust' => 'integer',
            'loyalty' => 'integer',
            'fear' => 'integer',
            'obligation' => 'integer',
            'revision' => 'integer',
        ];
    }
}
