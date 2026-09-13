<?php

namespace App\Models;

use Database\Factories\WorldRelationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use InvalidArgumentException;

#[Fillable([
    'chronicle_id',
    'source_entity_id',
    'target_entity_id',
    'relation_type_id',
    'weight',
    'note',
    'started_at',
    'ended_at',
    'provenance',
])]
class WorldRelation extends Model
{
    /** @use HasFactory<WorldRelationFactory> */
    use HasFactory;

    /**
     * @param  Builder<WorldRelation>  $query
     * @return Builder<WorldRelation>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }

    /**
     * @return BelongsTo<Chronicle, $this>
     */
    public function chronicle(): BelongsTo
    {
        return $this->belongsTo(Chronicle::class);
    }

    /**
     * @return BelongsTo<WorldEntity, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(WorldEntity::class, 'source_entity_id');
    }

    /**
     * @return BelongsTo<WorldEntity, $this>
     */
    public function target(): BelongsTo
    {
        return $this->belongsTo(WorldEntity::class, 'target_entity_id');
    }

    /**
     * @return BelongsTo<WorldRelationType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(WorldRelationType::class, 'relation_type_id');
    }

    /**
     * Shared-PK subtype. Inverse belongsTo on CharacterRelationship is omitted.
     *
     * @return HasOne<CharacterRelationship, $this>
     */
    public function characterRelationship(): HasOne
    {
        return $this->hasOne(CharacterRelationship::class, 'id', 'id');
    }

    /**
     * Shared-PK subtype. Inverse belongsTo on CharacterAffiliation is omitted.
     *
     * @return HasOne<CharacterAffiliation, $this>
     */
    public function characterAffiliation(): HasOne
    {
        return $this->hasOne(CharacterAffiliation::class, 'id', 'id');
    }

    public function other(WorldEntity $entity): WorldEntity
    {
        if ((int) $this->source_entity_id === (int) $entity->id) {
            return $this->target;
        }

        if ((int) $this->target_entity_id === (int) $entity->id) {
            return $this->source;
        }

        throw new InvalidArgumentException('Entity is not an endpoint of this relation.');
    }

    public function isActive(): bool
    {
        return $this->ended_at === null;
    }

    protected function casts(): array
    {
        return [
            'chronicle_id' => 'integer',
            'source_entity_id' => 'integer',
            'target_entity_id' => 'integer',
            'relation_type_id' => 'integer',
            'weight' => 'float',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'provenance' => 'array',
        ];
    }
}
