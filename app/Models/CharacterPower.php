<?php

namespace App\Models;

use Database\Factories\CharacterPowerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
    /** @use HasFactory<CharacterPowerFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Character, $this>
     */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    /**
     * @return BelongsTo<Discipline, $this>
     */
    public function discipline(): BelongsTo
    {
        return $this->belongsTo(Discipline::class);
    }

    /**
     * @return BelongsTo<DisciplinePower, $this>
     */
    public function power(): BelongsTo
    {
        return $this->belongsTo(DisciplinePower::class, 'discipline_power_id');
    }

    protected function casts(): array
    {
        return [
            'acquired_at' => 'datetime',
            'parameters' => 'array',
        ];
    }
}
