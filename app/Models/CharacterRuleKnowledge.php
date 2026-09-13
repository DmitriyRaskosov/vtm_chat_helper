<?php

namespace App\Models;

use App\Enums\CharacterKnowledgeLevel;
use Database\Factories\CharacterRuleKnowledgeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'character_id',
    'rule_document_id',
    'knowledge_level',
    'confidence',
    'learned_at',
    'approved_at',
    'approved_by',
])]
class CharacterRuleKnowledge extends Model
{
    /** @use HasFactory<CharacterRuleKnowledgeFactory> */
    use HasFactory;

    protected $table = 'character_rule_knowledge';

    /**
     * @return BelongsTo<Character, $this>
     */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    /**
     * @return BelongsTo<RuleDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(RuleDocument::class, 'rule_document_id');
    }

    protected function casts(): array
    {
        return [
            'character_id' => 'integer',
            'rule_document_id' => 'integer',
            'knowledge_level' => CharacterKnowledgeLevel::class,
            'confidence' => 'integer',
            'learned_at' => 'datetime',
            'approved_at' => 'datetime',
            'approved_by' => 'integer',
        ];
    }
}
