<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'slug',
    'name',
    'description',
    'is_common',
])]
class CanonDiscipline extends Model
{
    protected function casts(): array
    {
        return [
            'is_common' => 'boolean',
        ];
    }

    public function powers(): HasMany
    {
        return $this->hasMany(CanonDisciplinePower::class, 'discipline_id');
    }

    public function clans(): BelongsToMany
    {
        return $this->belongsToMany(CanonClan::class, 'canon_clan_disciplines', 'discipline_id', 'clan_id')
            ->withPivot('is_in_clan');
    }

    /**
     * пригодится позже для аналитики
     */
    public function characterDisciplines(): HasMany
    {
        return $this->hasMany(CharacterDiscipline::class, 'discipline_id');
    }
}