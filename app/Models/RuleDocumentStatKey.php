<?php

namespace App\Models;

use App\Enums\CharacterStatCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'rule_document_id',
    'stat_category',
    'stat_key',
])]
class RuleDocumentStatKey extends Model
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
            'stat_category' => CharacterStatCategory::class,
        ];
    }
}
