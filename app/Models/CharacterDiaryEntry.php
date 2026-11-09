<?php

namespace App\Models;

use App\Enums\DiaryEmotion;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'character_id',
    'chronicle_id',
    'scene_id',
    'combat_encounter_id',
    'level',
    'from_message_id',
    'to_message_id',
    'entry',
    'is_stale',
    'is_truncated',
    'importance',
    'emotion',
    'in_game_date',
])]
class CharacterDiaryEntry extends Model
{
    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'from_message_id' => 'integer',
            'to_message_id' => 'integer',
            'is_stale' => 'boolean',
            'is_truncated' => 'boolean',
            'importance' => 'float',
            'emotion' => DiaryEmotion::class,
            'in_game_date' => 'datetime',
        ];
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    public function chronicle(): BelongsTo
    {
        return $this->belongsTo(Chronicle::class);
    }

    public function scene(): BelongsTo
    {
        return $this->belongsTo(Scene::class);
    }
}
