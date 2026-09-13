<?php

namespace App\Models;

use App\Enums\CharacterStatCategory;
use Database\Factories\CharacterStatFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'character_id',
    'category',
    'stat_key',
    'display_name',
    'value',
    'maximum',
    'sort_order',
    'metadata',
])]
class CharacterStat extends Model
{
    /** @use HasFactory<CharacterStatFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Character, $this>
     */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    /**
     * @return HasMany<CharacterStatSpecialization, $this>
     */
    public function specializations(): HasMany
    {
        return $this->hasMany(CharacterStatSpecialization::class);
    }

    protected function casts(): array
    {
        return [
            'category' => CharacterStatCategory::class,
            'value' => 'integer',
            'maximum' => 'integer',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }
}
