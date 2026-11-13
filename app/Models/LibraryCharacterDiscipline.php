<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'library_character_id',
    'discipline_id',
    'level',
])]
class LibraryCharacterDiscipline extends Model
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

    protected function casts(): array
    {
        return [
            'library_character_id' => 'integer',
            'discipline_id' => 'integer',
            'level' => 'integer',
        ];
    }
}
