<?php

namespace App\Models;

use Database\Factories\CharacterStatSpecializationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'character_stat_id',
    'name',
    'description',
    'is_active',
])]
class CharacterStatSpecialization extends Model
{
    /** @use HasFactory<CharacterStatSpecializationFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<CharacterStat, $this>
     */
    public function stat(): BelongsTo
    {
        return $this->belongsTo(CharacterStat::class, 'character_stat_id');
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
