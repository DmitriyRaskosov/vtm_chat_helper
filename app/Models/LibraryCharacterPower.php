<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'library_character_id',
    'discipline_id',
    'discipline_power_id',
    'acquired_at',
    'note',
    'parameters',
])]
class LibraryCharacterPower extends Model
{
    /**
     * @return BelongsTo<LibraryCharacter, $this>
     */
    public function libraryCharacter(): BelongsTo
    {
        return $this->belongsTo(LibraryCharacter::class);
    }

    /**
     * @return BelongsTo<CanonDiscipline, $this>
     */
    public function discipline(): BelongsTo
    {
        return $this->belongsTo(CanonDiscipline::class, 'discipline_id');
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
            'library_character_id' => 'integer',
            'discipline_id' => 'integer',
            'discipline_power_id' => 'integer',
            'acquired_at' => 'datetime',
            'parameters' => 'array',
        ];
    }
}
