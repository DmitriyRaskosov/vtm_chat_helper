<?php

namespace App\Models;

use App\Enums\CharacterMemoryNodeType;
use App\Enums\CharacterMemoryStatus;
use Database\Factories\CharacterMemoryNodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Pgvector\Laravel\HasNeighbors;
use Pgvector\Laravel\Vector;

#[Fillable([
    'character_id',
    'node_text',
    'node_type',
    'importance',
    'emotional_valence',
    'arousal',
    'confidence',
    'is_false_belief',
    'recall_count',
    'last_recalled_at',
    'last_recall_score',
    'status',
    'aliases',
    'provenance',
    'approved_at',
    'approved_by',
    'created_by',
    'embedding',
])]
class CharacterMemoryNode extends Model
{
    /** @use HasFactory<CharacterMemoryNodeFactory> */
    use HasFactory;

    use HasNeighbors;

    /**
     * @return BelongsTo<Character, $this>
     */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    /**
     * @return HasMany<MemoryNodeEntity, $this>
     */
    public function entityLinks(): HasMany
    {
        return $this->hasMany(MemoryNodeEntity::class, 'memory_node_id');
    }

    /**
     * @return HasMany<MemoryNodeMessage, $this>
     */
    public function messageLinks(): HasMany
    {
        return $this->hasMany(MemoryNodeMessage::class, 'memory_node_id');
    }

    /**
     * @return HasMany<MemoryNodeEvent, $this>
     */
    public function eventLinks(): HasMany
    {
        return $this->hasMany(MemoryNodeEvent::class, 'memory_node_id');
    }

    /**
     * @return HasMany<MemoryNodeLoreEntry, $this>
     */
    public function loreLinks(): HasMany
    {
        return $this->hasMany(MemoryNodeLoreEntry::class, 'memory_node_id');
    }

    /**
     * @return HasMany<MemoryNodeScene, $this>
     */
    public function sceneLinks(): HasMany
    {
        return $this->hasMany(MemoryNodeScene::class, 'memory_node_id');
    }

    protected function casts(): array
    {
        return [
            'character_id' => 'integer',
            'node_type' => CharacterMemoryNodeType::class,
            'importance' => 'integer',
            'emotional_valence' => 'integer',
            'arousal' => 'integer',
            'confidence' => 'integer',
            'is_false_belief' => 'boolean',
            'recall_count' => 'integer',
            'last_recalled_at' => 'datetime',
            'last_recall_score' => 'float',
            'status' => CharacterMemoryStatus::class,
            'aliases' => 'array',
            'provenance' => 'array',
            'approved_at' => 'datetime',
            'approved_by' => 'integer',
            'created_by' => 'integer',
            'embedding' => Vector::class,
        ];
    }
}
