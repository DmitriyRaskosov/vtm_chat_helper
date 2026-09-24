<?php

namespace App\Models;

use App\Character\BiographyVersionImmutableException;
use Database\Factories\CharacterBiographyVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'character_id',
    'version',
    'summary',
    'full_text',
    'principles',
    'motivation',
    'fears',
    'desires',
    'behavioral_rules',
    'change_reason',
    'created_by',
])]
class CharacterBiographyVersion extends Model
{
    /** @use HasFactory<CharacterBiographyVersionFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new BiographyVersionImmutableException;
        });

        static::deleting(function (): never {
            throw new BiographyVersionImmutableException;
        });
    }

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
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return [
            'character_id' => 'integer',
            'version' => 'integer',
            'created_by' => 'integer',
        ];
    }
}
