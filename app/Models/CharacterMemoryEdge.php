<?php

namespace App\Models;

use App\Enums\CharacterMemoryEdgeType;
use Database\Factories\CharacterMemoryEdgeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'character_id',
    'source_node_id',
    'target_node_id',
    'relation_type',
    'authored_weight',
    'bidirectional',
    'traversal_count',
    'last_traversed_at',
    'provenance',
    'approved_at',
    'approved_by',
])]
class CharacterMemoryEdge extends Model
{
    /** @use HasFactory<CharacterMemoryEdgeFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Character, $this>
     */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    /**
     * @return BelongsTo<CharacterMemoryNode, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(CharacterMemoryNode::class, 'source_node_id');
    }

    /**
     * @return BelongsTo<CharacterMemoryNode, $this>
     */
    public function target(): BelongsTo
    {
        return $this->belongsTo(CharacterMemoryNode::class, 'target_node_id');
    }

    protected function casts(): array
    {
        return [
            'character_id' => 'integer',
            'source_node_id' => 'integer',
            'target_node_id' => 'integer',
            'relation_type' => CharacterMemoryEdgeType::class,
            'authored_weight' => 'float',
            'bidirectional' => 'boolean',
            'traversal_count' => 'integer',
            'last_traversed_at' => 'datetime',
            'provenance' => 'array',
            'approved_at' => 'datetime',
            'approved_by' => 'integer',
        ];
    }
}
