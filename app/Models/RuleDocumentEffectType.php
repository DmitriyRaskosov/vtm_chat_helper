<?php

namespace App\Models;

use App\Enums\CharacterStatusEffectType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'rule_document_id',
    'effect_type',
])]
class RuleDocumentEffectType extends Model
{
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
            'rule_document_id' => 'integer',
            'effect_type' => CharacterStatusEffectType::class,
        ];
    }
}
