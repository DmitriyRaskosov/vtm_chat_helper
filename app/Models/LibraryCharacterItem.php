<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'library_character_id',
    'name',
    'description',
    'quantity',
    'is_equipped',
])]
class LibraryCharacterItem extends Model
{
    /**
     * @return BelongsTo<LibraryCharacter, $this>
     */
    public function libraryCharacter(): BelongsTo
    {
        return $this->belongsTo(LibraryCharacter::class);
    }

    protected function casts(): array
    {
        return [
            'library_character_id' => 'integer',
            'quantity' => 'integer',
            'is_equipped' => 'boolean',
        ];
    }
}
