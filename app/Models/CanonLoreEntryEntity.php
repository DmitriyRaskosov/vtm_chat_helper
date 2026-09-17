<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CanonLoreEntryEntity extends Model
{
    protected $fillable = [
        'lore_entry_id',
        'entity_type',
        'entity_id',
        'note',
    ];

    /**
     * @return BelongsTo<CanonLoreEntry, $this>
     */
    public function loreEntry(): BelongsTo
    {
        return $this->belongsTo(CanonLoreEntry::class, 'lore_entry_id');
    }
}
