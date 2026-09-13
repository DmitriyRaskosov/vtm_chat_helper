<?php

namespace App\Models;

use Database\Factories\GameRuleChunkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Pgvector\Laravel\HasNeighbors;
use Pgvector\Laravel\Vector;

#[Fillable([
    'ruleset_id',
    'edition',
    'language',
    'rule_document_id',
    'rule_document_version_id',
    'chronicle_rule_override_id',
    'chronicle_id',
    'chunk_index',
    'section_path',
    'content',
    'source_reference',
    'token_estimate',
    'metadata',
    'embedding',
])]
class GameRuleChunk extends Model
{
    /** @use HasFactory<GameRuleChunkFactory> */
    use HasFactory;

    use HasNeighbors;

    /**
     * @return BelongsTo<Ruleset, $this>
     */
    public function ruleset(): BelongsTo
    {
        return $this->belongsTo(Ruleset::class);
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
            'ruleset_id' => 'integer',
            'rule_document_id' => 'integer',
            'rule_document_version_id' => 'integer',
            'chronicle_rule_override_id' => 'integer',
            'chronicle_id' => 'integer',
            'chunk_index' => 'integer',
            'token_estimate' => 'integer',
            'metadata' => 'array',
            'embedding' => Vector::class,
        ];
    }
}
