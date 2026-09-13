<?php

namespace App\Models;

use App\Enums\RuleDocumentStatus;
use App\Rulebook\RuleVersionImmutableException;
use Database\Factories\RuleDocumentVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'rule_document_id',
    'version',
    'title',
    'section',
    'canonical_text',
    'source_reference',
    'status',
    'change_reason',
    'created_by',
])]
class RuleDocumentVersion extends Model
{
    /** @use HasFactory<RuleDocumentVersionFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new RuleVersionImmutableException;
        });

        static::deleting(function (): never {
            throw new RuleVersionImmutableException;
        });
    }

    /**
     * @return BelongsTo<RuleDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(RuleDocument::class, 'rule_document_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return [
            'rule_document_id' => 'integer',
            'version' => 'integer',
            'status' => RuleDocumentStatus::class,
            'created_by' => 'integer',
        ];
    }
}
