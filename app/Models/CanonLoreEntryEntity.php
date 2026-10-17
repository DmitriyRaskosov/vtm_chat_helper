<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'lore_entry_id',
    'entity_type',
    'entity_id',
    'note',
])]
class CanonLoreEntryEntity extends Model
{
    /**
     * @return BelongsTo<CanonLoreEntry, $this>
     */
    public function loreEntry(): BelongsTo
    {
        return $this->belongsTo(CanonLoreEntry::class, 'lore_entry_id');
    }
}
