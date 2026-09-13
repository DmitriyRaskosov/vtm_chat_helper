<?php

namespace App\Models;

use App\Enums\LoreChunkSection;
use App\Enums\LoreVisibility;
use Database\Factories\LoreChunkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Pgvector\Laravel\HasNeighbors;
use Pgvector\Laravel\Vector;

#[Fillable([
    'lore_entry_version_id',
    'lore_entry_id',
    'chronicle_id',
    'chunk_index',
    'section',
    'content',
    'token_estimate',
    'visibility',
    'metadata',
    'embedding',
])]
class LoreChunk extends Model
{
    /** @use HasFactory<LoreChunkFactory> */
    use HasFactory;

    use HasNeighbors;

    /**
     * @return BelongsTo<LoreEntryVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(LoreEntryVersion::class, 'lore_entry_version_id');
    }

    /**
     * @return BelongsTo<LoreEntry, $this>
     */
    public function loreEntry(): BelongsTo
    {
        return $this->belongsTo(LoreEntry::class);
    }

    protected function casts(): array
    {
        return [
            'lore_entry_version_id' => 'integer',
            'lore_entry_id' => 'integer',
            'chronicle_id' => 'integer',
            'chunk_index' => 'integer',
            'section' => LoreChunkSection::class,
            'token_estimate' => 'integer',
            'visibility' => LoreVisibility::class,
            'metadata' => 'array',
            'embedding' => Vector::class,
        ];
    }
}
