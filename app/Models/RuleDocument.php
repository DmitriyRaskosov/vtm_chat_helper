<?php

namespace App\Models;

use App\Enums\RuleDocumentStatus;
use App\Rulebook\CannotDeleteRuleDocumentException;
use Database\Factories\RuleDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'ruleset_id',
    'title',
    'section',
    'canonical_text',
    'source_reference',
    'current_version',
    'status',
    'created_by',
    'approved_at',
    'approved_by',
])]
class RuleDocument extends Model
{
    /** @use HasFactory<RuleDocumentFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::deleting(function (): never {
            throw new CannotDeleteRuleDocumentException;
        });
    }

    /**
     * @return BelongsTo<Ruleset, $this>
     */
    public function ruleset(): BelongsTo
    {
        return $this->belongsTo(Ruleset::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<RuleDocumentVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(RuleDocumentVersion::class);
    }

    /**
     * @return HasMany<ChronicleRuleOverride, $this>
     */
    public function chronicleOverrides(): HasMany
    {
        return $this->hasMany(ChronicleRuleOverride::class);
    }

    /**
     * @return BelongsToMany<Discipline, $this>
     */
    public function disciplines(): BelongsToMany
    {
        return $this->belongsToMany(Discipline::class, 'rule_document_disciplines')->withTimestamps();
    }

    /**
     * @return BelongsToMany<DisciplinePower, $this>
     */
    public function powers(): BelongsToMany
    {
        return $this->belongsToMany(DisciplinePower::class, 'rule_document_powers')->withTimestamps();
    }

    protected function casts(): array
    {
        return [
            'ruleset_id' => 'integer',
            'current_version' => 'integer',
            'status' => RuleDocumentStatus::class,
            'created_by' => 'integer',
            'approved_at' => 'datetime',
            'approved_by' => 'integer',
        ];
    }
}
