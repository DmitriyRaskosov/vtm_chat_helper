<?php

namespace App\Models;

use Database\Factories\DisciplinePowerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'discipline_id',
    'key',
    'display_name',
    'required_level',
    'rule_key',
])]
class DisciplinePower extends Model
{
    /** @use HasFactory<DisciplinePowerFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Discipline, $this>
     */
    public function discipline(): BelongsTo
    {
        return $this->belongsTo(Discipline::class);
    }

    /**
     * @return HasMany<CharacterPower, $this>
     */
    public function characterPowers(): HasMany
    {
        return $this->hasMany(CharacterPower::class);
    }

    protected function casts(): array
    {
        return [
            'required_level' => 'integer',
        ];
    }
}
