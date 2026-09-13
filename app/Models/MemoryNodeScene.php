<?php

namespace App\Models;

use Database\Factories\MemoryNodeSceneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'memory_node_id',
    'character_id',
    'scene_id',
])]
class MemoryNodeScene extends Model
{
    /** @use HasFactory<MemoryNodeSceneFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<CharacterMemoryNode, $this>
     */
    public function node(): BelongsTo
    {
        return $this->belongsTo(CharacterMemoryNode::class, 'memory_node_id');
    }

    /**
     * @return BelongsTo<Scene, $this>
     */
    public function scene(): BelongsTo
    {
        return $this->belongsTo(Scene::class);
    }

    protected function casts(): array
    {
        return [
            'memory_node_id' => 'integer',
            'character_id' => 'integer',
            'scene_id' => 'integer',
        ];
    }
}
