<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'character_id',
    'name',
    'description',
    'quantity',
    'is_equipped',
])]
class CharacterItem extends Model
{
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
            'character_id' => 'integer',
            'quantity' => 'integer',
            'is_equipped' => 'boolean',
        ];
    }
}
