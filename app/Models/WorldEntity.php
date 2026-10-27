<?php

namespace App\Models;

use App\Enums\WorldEntityStatus;
use App\Enums\WorldEntityType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property WorldEntityType $entity_type
 * @property WorldEntityStatus $status
 */
#[Fillable([
    'chronicle_id',
    'entity_type',
    'canonical_name',
    'slug',
    'short_description',
    'status',
    'archived_at',
])]
class WorldEntity extends Model
{
    /**
     * @param  Builder<WorldEntity>  $query
     * @return Builder<WorldEntity>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', WorldEntityStatus::Active);
    }

    /**
     * @return BelongsTo<Chronicle, $this>
     */
    public function chronicle(): BelongsTo
    {
        return $this->belongsTo(Chronicle::class);
    }

    /**
     * Подтип с общим PK. Обратный belongsTo на подтипе не объявляем: связка
     * HasOne + belongsTo на одном `id` уходит в рекурсию до падения PHP.
     *
     * @return HasOne<Location, $this>
     */
    public function location(): HasOne
    {
        return $this->hasOne(Location::class, 'id', 'id');
    }

    /**
     * @return HasOne<Character, $this>
     */
    public function character(): HasOne
    {
        return $this->hasOne(Character::class, 'id', 'id');
    }

    /**
     * @return HasMany<WorldRelation, $this>
     */
    public function outgoingRelations(): HasMany
    {
        return $this->hasMany(WorldRelation::class, 'source_id')
            ->where('source_type', $this->entity_type->value);
    }

    /**
     * @return HasMany<WorldRelation, $this>
     */
    public function incomingRelations(): HasMany
    {
        return $this->hasMany(WorldRelation::class, 'target_id')
            ->where('target_type', $this->entity_type->value);
    }

    protected function casts(): array
    {
        return [
            'entity_type' => WorldEntityType::class,
            'status' => WorldEntityStatus::class,
            'archived_at' => 'datetime',
        ];
    }
}
