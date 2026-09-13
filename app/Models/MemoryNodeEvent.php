<?php

namespace App\Models;

use Database\Factories\MemoryNodeEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'memory_node_id',
    'character_id',
    'event_id',
    'chronicle_id',
])]
class MemoryNodeEvent extends Model
{
    /** @use HasFactory<MemoryNodeEventFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<CharacterMemoryNode, $this>
     */
    public function node(): BelongsTo
    {
        return $this->belongsTo(CharacterMemoryNode::class, 'memory_node_id');
    }

    /**
     * @return BelongsTo<WorldEvent, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(WorldEvent::class, 'event_id');
    }

    protected function casts(): array
    {
        return [
            'memory_node_id' => 'integer',
            'character_id' => 'integer',
            'event_id' => 'integer',
            'chronicle_id' => 'integer',
        ];
    }
}
