<?php

namespace App\Models;

use App\Enums\WorldEntityType;
use Database\Factories\WorldRelationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

#[Fillable([
    'chronicle_id',
    'source_type',
    'source_id',
    'target_type',
    'target_id',
    'relation',
    'source_of_truth',
    'intensity',
    'metadata',
    'weight',
    'note',
    'valid_from',
    'valid_to',
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
        return $query->whereNull('valid_to');
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
        return $this->belongsTo(WorldEntity::class, 'source_id');
    }

    /**
     * @return BelongsTo<WorldEntity, $this>
     */
    public function target(): BelongsTo
    {
        return $this->belongsTo(WorldEntity::class, 'target_id');
    }

    public function relationType(): ?WorldRelationType
    {
        return WorldRelationType::query()->where('key', $this->relation)->first();
    }

    public function other(WorldEntity $entity): WorldEntity
    {
        if ((int) $this->source_id === (int) $entity->id
            && $this->endpointType($entity) === $this->source_type) {
            return $this->target;
        }

        if ((int) $this->target_id === (int) $entity->id
            && $this->endpointType($entity) === $this->target_type) {
            return $this->source;
        }

        throw new InvalidArgumentException('Entity is not an endpoint of this relation.');
    }

    public function isActive(): bool
    {
        return $this->valid_to === null;
    }

    public function endpointType(WorldEntity $entity): string
    {
        $type = $entity->entity_type;

        return $type instanceof WorldEntityType ? $type->value : (string) $type;
    }

    protected function casts(): array
    {
        return [
            'chronicle_id' => 'integer',
            'source_id' => 'integer',
            'target_id' => 'integer',
            'intensity' => 'integer',
            'weight' => 'float',
            'metadata' => 'array',
            'valid_from' => 'datetime',
            'valid_to' => 'datetime',
            'provenance' => 'array',
        ];
    }
}
