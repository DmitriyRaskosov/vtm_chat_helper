<?php

namespace App\Models;

use Database\Factories\MemoryNodeLoreEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'memory_node_id',
    'character_id',
    'lore_entry_id',
    'chronicle_id',
])]
class MemoryNodeLoreEntry extends Model
{
    /** @use HasFactory<MemoryNodeLoreEntryFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<CharacterMemoryNode, $this>
     */
    public function node(): BelongsTo
    {
        return $this->belongsTo(CharacterMemoryNode::class, 'memory_node_id');
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
            'memory_node_id' => 'integer',
            'character_id' => 'integer',
            'lore_entry_id' => 'integer',
            'chronicle_id' => 'integer',
        ];
    }
}
