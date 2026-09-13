<?php

namespace App\Models;

use App\Enums\RuleDocumentStatus;
use Database\Factories\ChronicleRuleOverrideFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'chronicle_id',
    'rule_document_id',
    'override_text',
    'status',
    'current_version',
    'change_reason',
    'created_by',
    'approved_at',
    'approved_by',
])]
class ChronicleRuleOverride extends Model
{
    /** @use HasFactory<ChronicleRuleOverrideFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Chronicle, $this>
     */
    public function chronicle(): BelongsTo
    {
        return $this->belongsTo(Chronicle::class);
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
            'chronicle_id' => 'integer',
            'rule_document_id' => 'integer',
            'status' => RuleDocumentStatus::class,
            'current_version' => 'integer',
            'created_by' => 'integer',
            'approved_at' => 'datetime',
            'approved_by' => 'integer',
        ];
    }
}
