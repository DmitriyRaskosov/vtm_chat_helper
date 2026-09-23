<?php

namespace App\Models;

use App\Enums\CharacterBiographyStatus;
use Database\Factories\CharacterBiographyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'character_id',
    'summary',
    'full_text',
    'principles',
    'motivation',
    'fears',
    'desires',
    'behavioral_rules',
    'current_version',
    'status',
    'approved_at',
    'approved_by',
])]
class CharacterBiography extends Model
{
    /** @use HasFactory<CharacterBiographyFactory> */
    use HasFactory;

    protected $primaryKey = 'character_id';

    public $incrementing = false;

    /**
     * @return BelongsTo<Character, $this>
     */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<CharacterBiographyVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(CharacterBiographyVersion::class, 'character_id', 'character_id');
    }

    protected function casts(): array
    {
        return [
            'character_id' => 'integer',
            'current_version' => 'integer',
            'status' => CharacterBiographyStatus::class,
            'approved_at' => 'datetime',
            'approved_by' => 'integer',
        ];
    }
}
