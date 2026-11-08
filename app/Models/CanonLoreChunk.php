<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'canon_lore_entry_id',
    'header',
    'content',
    'token_estimate',
])]
class CanonLoreChunk extends Model
{
    /**
     * @return BelongsTo<CanonLoreEntry, $this>
     */
    public function loreEntry(): BelongsTo
    {
        return $this->belongsTo(CanonLoreEntry::class, 'canon_lore_entry_id');
    }

    protected function casts(): array
    {
        return [
            'canon_lore_entry_id' => 'integer',
            'token_estimate' => 'integer',
        ];
    }
}
