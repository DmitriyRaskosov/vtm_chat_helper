<?php

namespace App\Models;

use Database\Factories\LoreEntryEntityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'lore_entry_id',
    'entity_id',
    'chronicle_id',
    'role',
])]
class LoreEntryEntity extends Model
{
    /** @use HasFactory<LoreEntryEntityFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<LoreEntry, $this>
     */
    public function loreEntry(): BelongsTo
    {
        return $this->belongsTo(LoreEntry::class);
    }

    /**
     * @return BelongsTo<WorldEntity, $this>
     */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(WorldEntity::class, 'entity_id');
    }

    protected function casts(): array
    {
        return [
            'lore_entry_id' => 'integer',
            'entity_id' => 'integer',
            'chronicle_id' => 'integer',
        ];
    }
}
