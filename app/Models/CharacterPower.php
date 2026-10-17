<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'character_id',
    'discipline_id',
    'discipline_power_id',
    'acquired_at',
    'note',
    'parameters',
])]
class CharacterPower extends Model
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

    /**
     * @return BelongsTo<CanonDisciplinePower, $this>
     */
    public function power(): BelongsTo
    {
        return $this->belongsTo(CanonDisciplinePower::class, 'discipline_power_id');
    }

    protected function casts(): array
    {
        return [
            'acquired_at' => 'datetime',
            'parameters' => 'array',
        ];
    }
}
