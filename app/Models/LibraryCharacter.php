<?php

namespace App\Models;

use App\Enums\CharacterType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property CharacterType $character_type
 * @property array<string, mixed>|null $stats
 * @property array<string, mixed>|null $sheet_data
 */
#[Fillable([
    'user_id',
    'name',
    'character_type',
    'clan_id',
    'sect_id',
    'nature',
    'demeanor',
    'concept',
    'generation',
    'stats',
    'sheet_data',
])]
class LibraryCharacter extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<CanonClan, $this>
     */
    public function clan(): BelongsTo
    {
        return $this->belongsTo(CanonClan::class, 'clan_id');
    }

    /**
     * @return BelongsTo<CanonSect, $this>
     */
    public function sect(): BelongsTo
    {
        return $this->belongsTo(CanonSect::class, 'sect_id');
    }

    /**
     * @return HasOne<LibraryCharacterBiography, $this>
     */
    public function biography(): HasOne
    {
        return $this->hasOne(LibraryCharacterBiography::class);
    }

    /**
     * @return HasMany<LibraryCharacterDiscipline, $this>
     */
    public function disciplines(): HasMany
    {
        return $this->hasMany(LibraryCharacterDiscipline::class);
    }

    /**
     * @return HasMany<LibraryCharacterPower, $this>
     */
    public function powers(): HasMany
    {
        return $this->hasMany(LibraryCharacterPower::class);
    }

    /**
     * @return HasMany<LibraryCharacterItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(LibraryCharacterItem::class);
    }

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'character_type' => CharacterType::class,
            'clan_id' => 'integer',
            'sect_id' => 'integer',
            'generation' => 'integer',
            'stats' => 'array',
            'sheet_data' => 'array',
        ];
    }
}
