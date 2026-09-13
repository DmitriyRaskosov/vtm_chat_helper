<?php

namespace App\Models;

use Database\Factories\CharacterAffiliationChangeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'affiliation_id',
    'character_id',
    'field',
    'old_value',
    'new_value',
    'reason',
    'scene_id',
    'message_id',
    'changed_by',
    'revision',
])]
class CharacterAffiliationChange extends Model
{
    /** @use HasFactory<CharacterAffiliationChangeFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<CharacterAffiliation, $this>
     */
    public function affiliation(): BelongsTo
    {
        return $this->belongsTo(CharacterAffiliation::class);
    }

    /**
     * @return BelongsTo<Character, $this>
     */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    /**
     * @return BelongsTo<Scene, $this>
     */
    public function scene(): BelongsTo
    {
        return $this->belongsTo(Scene::class);
    }

    /**
     * @return BelongsTo<Message, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    protected function casts(): array
    {
        return [
            'old_value' => 'array',
            'new_value' => 'array',
            'revision' => 'integer',
        ];
    }
}
