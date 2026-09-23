<?php

namespace App\Models;

use App\Enums\SceneParticipantRole;
use Database\Factories\SceneParticipantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'scene_id',
    'chronicle_id',
    'character_id',
    'role',
    'visible',
    'is_current',
    'entered_at',
    'left_at',
])]
class SceneParticipant extends Model
{
    /** @use HasFactory<SceneParticipantFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Scene, $this>
     */
    public function scene(): BelongsTo
    {
        return $this->belongsTo(Scene::class);
    }

    /**
     * @return BelongsTo<Chronicle, $this>
     */
    public function chronicle(): BelongsTo
    {
        return $this->belongsTo(Chronicle::class);
    }

    /**
     * @return BelongsTo<Character, $this>
     */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    protected function casts(): array
    {
        return [
            'scene_id' => 'integer',
            'chronicle_id' => 'integer',
            'character_id' => 'integer',
            'role' => SceneParticipantRole::class,
            'visible' => 'boolean',
            'is_current' => 'boolean',
            'entered_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }
}
