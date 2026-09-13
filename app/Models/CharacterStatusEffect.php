<?php

namespace App\Models;

use App\Enums\CharacterStatusEffectType;
use Database\Factories\CharacterStatusEffectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'character_id',
    'effect_type',
    'description',
    'modifier',
    'active_from',
    'active_until',
    'source_type',
    'source_id',
    'is_active',
])]
class CharacterStatusEffect extends Model
{
    /** @use HasFactory<CharacterStatusEffectFactory> */
    use HasFactory;

    /**
     * @param  Builder<CharacterStatusEffect>  $query
     * @return Builder<CharacterStatusEffect>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @return BelongsTo<Character, $this>
     */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    protected function casts(): array
    {
        return [
            'effect_type' => CharacterStatusEffectType::class,
            'modifier' => 'array',
            'active_from' => 'datetime',
            'active_until' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}
