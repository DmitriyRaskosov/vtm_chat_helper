<?php

namespace App\Models;

use App\Enums\RulesetStatus;
use Database\Factories\RulesetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'edition',
    'language',
    'status',
    'description',
])]
class Ruleset extends Model
{
    /** @use HasFactory<RulesetFactory> */
    use HasFactory;

    /**
     * @return HasMany<RuleDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(RuleDocument::class);
    }

    protected function casts(): array
    {
        return [
            'status' => RulesetStatus::class,
        ];
    }
}
