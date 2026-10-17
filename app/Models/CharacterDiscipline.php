<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'character_id',
    'discipline_id',
    'level',
])]
class CharacterDiscipline extends Model
{
    /**
     * @return BelongsTo<Character, $this>
     */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    /**
     * @return BelongsTo<CanonDiscipline, $this>
     */
    public function discipline(): BelongsTo
    {
        return $this->belongsTo(CanonDiscipline::class);
    }

    protected function casts(): array
    {
        return [
            'level' => 'integer',
        ];
    }
}
