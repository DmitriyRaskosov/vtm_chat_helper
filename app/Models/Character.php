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
    'domitor_character_id',
    'generation',
    'apparent_age',
    'actual_age',
    'nature',
    'demeanor',
    'concept',
    //'lore_clearance_levels',
    'is_active',
   // 'experience',
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
     * @return BelongsTo<Character, $this>
     */
    public function domitor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'domitor_character_id');
    }

    /**
     * @return HasMany<Character, $this>
     */
    public function ghouls(): HasMany
    {
        return $this->hasMany(self::class, 'domitor_character_id');
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
     * @return HasMany<CharacterStat, $this>
     */
    public function stats(): HasMany
    {
        return $this->hasMany(CharacterStat::class);
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
     * @return HasOne<CharacterStatus, $this>
     */
    public function status(): HasOne
    {
        return $this->hasOne(CharacterStatus::class);
    }

    /**
     * @return HasMany<CharacterStatusEffect, $this>
     */
    public function statusEffects(): HasMany
    {
        return $this->hasMany(CharacterStatusEffect::class);
    }

    /**
     * @return HasMany<CharacterStatusChange, $this>
     */
    public function statusChanges(): HasMany
    {
        return $this->hasMany(CharacterStatusChange::class);
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
     * @return HasMany<CharacterBioChunk, $this>
     */
    public function bioChunks(): HasMany
    {
        return $this->hasMany(CharacterBioChunk::class);
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

    /**
     * @return HasMany<CharacterMeritFlaw, $this>
     */
    public function meritsFlaws(): HasMany
    {
        return $this->hasMany(CharacterMeritFlaw::class);
    }

    /**
     * @return HasMany<CharacterHealthBox, $this>
     */
    public function healthBoxes(): HasMany
    {
        return $this->hasMany(CharacterHealthBox::class);
    }

    /**
     * @return HasMany<CharacterMemoryNode, $this>
     */
    public function memoryNodes(): HasMany
    {
        return $this->hasMany(CharacterMemoryNode::class);
    }

    public function isNpc(): bool
    {
        return $this->character_type === CharacterType::Npc;
    }

    public function isGhoul(): bool
    {
        return $this->character_type === CharacterType::Ghoul;
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

        if ($this->character_type !== CharacterType::Ghoul) {
            return false;
        }

        $domitorUserId = $this->relationLoaded('domitor')
            ? $this->domitor?->user_id
            : self::query()->whereKey($this->domitor_character_id)->value('user_id');

        return $domitorUserId !== null && (int) $domitorUserId === (int) $user->id;
    }

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'character_type' => CharacterType::class,
            'lore_clearance_levels' => PostgresIntegerArray::class,
            'domitor_character_id' => 'integer',
            'generation' => 'integer',
            'apparent_age' => 'integer',
            'actual_age' => 'integer',
            'is_active' => 'boolean',
            'experience' => 'integer',
        ];
    }
}
