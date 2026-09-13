<?php

namespace App\Models;

use Database\Factories\DisciplineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'ruleset',
    'key',
    'display_name',
])]
class Discipline extends Model
{
    /** @use HasFactory<DisciplineFactory> */
    use HasFactory;

    /**
     * @return HasMany<DisciplinePower, $this>
     */
    public function powers(): HasMany
    {
        return $this->hasMany(DisciplinePower::class);
    }

    /**
     * @return HasMany<CharacterDiscipline, $this>
     */
    public function characterDisciplines(): HasMany
    {
        return $this->hasMany(CharacterDiscipline::class);
    }
}
