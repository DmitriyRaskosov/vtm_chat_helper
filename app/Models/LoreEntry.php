<?php

namespace App\Models;

use App\Enums\LoreEntryKind;
use App\Enums\LoreEntryStatus;
use App\Enums\LoreVisibility;
use App\Lore\CannotDeleteLoreEntryException;
use Database\Factories\LoreEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'chronicle_id',
    'title',
    'kind',
    'canonical_text',
    'status',
    'visibility',
    'current_version',
    'legacy_source_id',
    'created_by',
    'approved_at',
    'approved_by',
])]
class LoreEntry extends Model
{
    /** @use HasFactory<LoreEntryFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::deleting(function (): never {
            throw new CannotDeleteLoreEntryException;
        });
    }

    /**
     * @return BelongsTo<Chronicle, $this>
     */
    public function chronicle(): BelongsTo
    {
        return $this->belongsTo(Chronicle::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<LoreEntryVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(LoreEntryVersion::class);
    }

    /**
     * @return HasMany<LoreEntryEntity, $this>
     */
    public function entityLinks(): HasMany
    {
        return $this->hasMany(LoreEntryEntity::class);
    }

    /**
     * @return BelongsToMany<WorldEntity, $this>
     */
    public function entities(): BelongsToMany
    {
        return $this->belongsToMany(WorldEntity::class, 'lore_entry_entities', 'lore_entry_id', 'entity_id')
            ->withPivot('role', 'chronicle_id')
            ->withTimestamps();
    }

    protected function casts(): array
    {
        return [
            'chronicle_id' => 'integer',
            'kind' => LoreEntryKind::class,
            'status' => LoreEntryStatus::class,
            'visibility' => LoreVisibility::class,
            'current_version' => 'integer',
            'created_by' => 'integer',
            'approved_at' => 'datetime',
            'approved_by' => 'integer',
        ];
    }
}
