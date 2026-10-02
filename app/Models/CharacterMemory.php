<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'character_id',
    'chronicle_id',
    'type',
    'content',
    'importance',
    'involved_entity_ids',
    'source_message_id',
    'source_scene_id',
])]
class CharacterMemory extends Model
{
    protected function casts(): array
    {
        return [
            'importance' => 'integer',
            'involved_entity_ids' => \App\Casts\PostgresIntegerArray::class,   // ← не 'array'
            'access_count' => 'integer',
            'last_accessed_at' => 'datetime',
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
}