<?php

namespace App\Models;

use App\Enums\CharacterKnowledgeLevel;
use App\Enums\LoreKnowledgeAccess;
use Database\Factories\CharacterLoreKnowledgeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'character_id',
    'lore_entry_id',
    'chronicle_id',
    'knowledge_level',
    'access',
    'confidence',
    'learned_at',
    'source_world_event_id',
    'source_memory_node_id',
    'approved_at',
    'approved_by',
])]
class CharacterLoreKnowledge extends Model
{
    /** @use HasFactory<CharacterLoreKnowledgeFactory> */
    use HasFactory;

    protected $table = 'character_lore_knowledge';

    /**
     * @return BelongsTo<Character, $this>
     */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    /**
     * @return BelongsTo<LoreEntry, $this>
     */
    public function loreEntry(): BelongsTo
    {
        return $this->belongsTo(LoreEntry::class);
    }

    /**
     * @return BelongsTo<WorldEvent, $this>
     */
    public function sourceEvent(): BelongsTo
    {
        return $this->belongsTo(WorldEvent::class, 'source_world_event_id');
    }

    /**
     * @return BelongsTo<CharacterMemoryNode, $this>
     */
    public function sourceMemory(): BelongsTo
    {
        return $this->belongsTo(CharacterMemoryNode::class, 'source_memory_node_id');
    }

    protected function casts(): array
    {
        return [
            'character_id' => 'integer',
            'lore_entry_id' => 'integer',
            'chronicle_id' => 'integer',
            'knowledge_level' => CharacterKnowledgeLevel::class,
            'access' => LoreKnowledgeAccess::class,
            'confidence' => 'integer',
            'learned_at' => 'datetime',
            'source_world_event_id' => 'integer',
            'source_memory_node_id' => 'integer',
            'approved_at' => 'datetime',
            'approved_by' => 'integer',
        ];
    }
}
