<?php

namespace App\Models;

use App\Models\CanonSect;
use App\Casts\PostgresIntegerArray;
use App\Enums\CharacterType;
use App\Enums\WorldEntityType;
use Database\Factories\CharacterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'id',
    'chronicle_id',
    'entity_type',
    'character_type',
    'user_id',
    'clan_id',
    'sire_character_id',
    'generation',
    'apparent_age',
    'actual_age',
    'nature',
    'demeanor',
    'concept',
    'is_active',
])]
class Character extends Model
{
    /** @use HasFactory<CharacterFactory> */
    use HasFactory;

    public $incrementing = false;

    /**
     * Shared-PK identity is read from WorldEntity::character(). Inverse belongsTo
     * on the same `id` is omitted: Eloquent recurses until PHP dies.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function clan(): BelongsTo
    {
        return $this->belongsTo(CanonClan::class, 'clan_id');
    }

    /**
     * @return BelongsTo<Character, $this>
     */
    public function sire(): BelongsTo
    {
        return $this->belongsTo(self::class, 'sire_character_id');
    }

    /**
     * @return HasMany<Character, $this>
     */
    public function childer(): HasMany
    {
        return $this->hasMany(self::class, 'sire_character_id');
    }

    /**
     * @return BelongsTo<Chronicle, $this>
     */
    public function chronicle(): BelongsTo
    {
        return $this->belongsTo(Chronicle::class);
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function authoredMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'author_character_id');
    }

    /**
     * @return HasMany<CharacterDiscipline, $this>
     */
    public function disciplines(): HasMany
    {
        return $this->hasMany(CharacterDiscipline::class);
    }

    /**
     * @return HasMany<CharacterPower, $this>
     */
    public function powers(): HasMany
    {
        return $this->hasMany(CharacterPower::class);
    }

    /**
     * @return HasOne<CharacterBiography, $this>
     */
    public function biography(): HasOne
    {
        return $this->hasOne(CharacterBiography::class);
    }

    /**
     * @return HasMany<CharacterBiographyVersion, $this>
     */
    public function biographyVersions(): HasMany
    {
        return $this->hasMany(CharacterBiographyVersion::class);
    }

    /**
     * @return HasMany<WorldRelation, $this>
     */
    public function outgoingRelations(): HasMany
    {
        return $this->hasMany(WorldRelation::class, 'source_id')
            ->where('source_type', 'character');
    }

    /**
     * @return HasMany<WorldRelation, $this>
     */
    public function incomingRelations(): HasMany
    {
        return $this->hasMany(WorldRelation::class, 'target_id')
            ->where('target_type', 'character');
    }

    public function isNpc(): bool
    {
        return $this->character_type === CharacterType::Npc;
    }

    public function sect(): BelongsTo
    {
    return $this->belongsTo(CanonSect::class, 'sect_id');
    }

    public function isPlayableBy(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if ($this->character_type === CharacterType::Player) {
            return (int) $this->user_id === (int) $user->id;
        }

        return false;
    }

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'character_type' => CharacterType::class,
            'generation' => 'integer',
            'apparent_age' => 'integer',
            'actual_age' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
