<?php

namespace App\Models;

use App\Enums\MemoryEntityRole;
use Database\Factories\MemoryNodeEntityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'memory_node_id',
    'character_id',
    'entity_id',
    'chronicle_id',
    'role',
])]
class MemoryNodeEntity extends Model
{
    /** @use HasFactory<MemoryNodeEntityFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<CharacterMemoryNode, $this>
     */
    public function node(): BelongsTo
    {
        return $this->belongsTo(CharacterMemoryNode::class, 'memory_node_id');
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
            'memory_node_id' => 'integer',
            'character_id' => 'integer',
            'entity_id' => 'integer',
            'chronicle_id' => 'integer',
            'role' => MemoryEntityRole::class,
        ];
    }
}
