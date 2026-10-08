<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'character_id',
    'chronicle_id',
    'scene_id',
    'level',
    'from_message_id',
    'to_message_id',
    'entry',
    'is_stale',
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