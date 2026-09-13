<?php

namespace App\Models;

use App\Enums\CharacterHealthDamage;
use Database\Factories\CharacterHealthBoxFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'character_id',
    'box_index',
    'damage',
])]
class CharacterHealthBox extends Model
{
    /** @use HasFactory<CharacterHealthBoxFactory> */
    use HasFactory;

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
            'box_index' => 'integer',
            'damage' => CharacterHealthDamage::class,
        ];
    }
}
