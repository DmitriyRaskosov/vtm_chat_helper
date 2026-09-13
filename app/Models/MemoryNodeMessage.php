<?php

namespace App\Models;

use Database\Factories\MemoryNodeMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'memory_node_id',
    'character_id',
    'message_id',
])]
class MemoryNodeMessage extends Model
{
    /** @use HasFactory<MemoryNodeMessageFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<CharacterMemoryNode, $this>
     */
    public function node(): BelongsTo
    {
        return $this->belongsTo(CharacterMemoryNode::class, 'memory_node_id');
    }

    /**
     * @return BelongsTo<Message, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    protected function casts(): array
    {
        return [
            'memory_node_id' => 'integer',
            'character_id' => 'integer',
            'message_id' => 'integer',
        ];
    }
}
