<?php

namespace App\Models;

use App\Enums\WorldEntityAliasType;
use App\Enums\WorldEntityStatus;
use App\Enums\WorldEntityType;
use App\World\CannotDeleteWorldEntityException;
use Database\Factories\WorldEntityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
    /** @use HasFactory<WorldEntityFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::deleting(function (): never {
            throw new CannotDeleteWorldEntityException;
        });
    }

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
     * @return HasMany<WorldEntityAlias, $this>
     */
    public function aliases(): HasMany
    {
        return $this->hasMany(WorldEntityAlias::class, 'entity_id');
    }

    /**
     * @return HasOne<WorldEntityAlias, $this>
     */
    public function canonicalAlias(): HasOne
    {
        return $this->hasOne(WorldEntityAlias::class, 'entity_id')->where(
            'alias_type',
            WorldEntityAliasType::Canonical->value,
        );
    }

    /**
     * Shared-PK subtype. Inverse belongsTo on the subtype is omitted: Eloquent
     * HasOne + belongsTo on the same `id` recurses until PHP dies.
     *
     * @return HasOne<Location, $this>
     */
    public function location(): HasOne
    {
        return $this->hasOne(Location::class, 'id', 'id');
    }

    /**
     * @return HasOne<Faction, $this>
     */
    public function faction(): HasOne
    {
        return $this->hasOne(Faction::class, 'id', 'id');
    }

    /**
     * @return HasOne<Clan, $this>
     */
    public function clan(): HasOne
    {
        return $this->hasOne(Clan::class, 'id', 'id');
    }

    /**
     * @return HasOne<Coterie, $this>
     */
    public function coterie(): HasOne
    {
        return $this->hasOne(Coterie::class, 'id', 'id');
    }

    /**
     * @return HasOne<Circle, $this>
     */
    public function circle(): HasOne
    {
        return $this->hasOne(Circle::class, 'id', 'id');
    }

    /**
     * @return HasOne<Other, $this>
     */
    public function other(): HasOne
    {
        return $this->hasOne(Other::class, 'id', 'id');
    }

    /**
     * @return HasOne<Item, $this>
     */
    public function item(): HasOne
    {
        return $this->hasOne(Item::class, 'id', 'id');
    }

    /**
     * @return HasOne<Concept, $this>
     */
    public function concept(): HasOne
    {
        return $this->hasOne(Concept::class, 'id', 'id');
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
        return $this->hasMany(WorldRelation::class, 'source_entity_id');
    }

    /**
     * @return HasMany<WorldRelation, $this>
     */
    public function incomingRelations(): HasMany
    {
        return $this->hasMany(WorldRelation::class, 'target_entity_id');
    }

    /**
     * Shared-PK subtype. Inverse belongsTo on WorldEvent is omitted.
     *
     * @return HasOne<WorldEvent, $this>
     */
    public function event(): HasOne
    {
        return $this->hasOne(WorldEvent::class, 'id', 'id');
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
