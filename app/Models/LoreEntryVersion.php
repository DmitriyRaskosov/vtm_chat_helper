<?php

namespace App\Models;

use App\Enums\LoreAccessLevel;
use App\Enums\LoreEntryKind;
use App\Enums\LoreEntryStatus;
use App\Enums\LoreVisibility;
use App\Lore\LoreVersionImmutableException;
use Database\Factories\LoreEntryVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'lore_entry_id',
    'chronicle_id',
    'version',
    'title',
    'kind',
    'canonical_text',
    'status',
    'visibility',
    'classification',
    'situational',
    'change_reason',
    'created_by',
])]
class LoreEntryVersion extends Model
{
    /** @use HasFactory<LoreEntryVersionFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LoreVersionImmutableException;
        });

        static::deleting(function (): never {
            throw new LoreVersionImmutableException;
        });
    }

    /**
     * @return BelongsTo<LoreEntry, $this>
     */
    public function loreEntry(): BelongsTo
    {
        return $this->belongsTo(LoreEntry::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return [
            'lore_entry_id' => 'integer',
            'chronicle_id' => 'integer',
            'version' => 'integer',
            'kind' => LoreEntryKind::class,
            'status' => LoreEntryStatus::class,
            'visibility' => LoreVisibility::class,
            'classification' => LoreAccessLevel::class,
            'situational' => 'boolean',
            'created_by' => 'integer',
        ];
    }
}
