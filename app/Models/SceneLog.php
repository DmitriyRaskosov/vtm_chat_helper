<?php

namespace App\Models;

use App\Enums\SceneLogType;
use App\Enums\SceneLogVisibility;
use Database\Factories\SceneLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property SceneLogType $type
 * @property SceneLogVisibility $visibility
 * @property array<string, mixed> $payload
 * @property bool $willpower_spent
 * @property Carbon $created_at
 */
#[Fillable([
    'scene_id',
    'user_id',
    'character_id',
    'type',
    'visibility',
    'message',
    'comment',
    'willpower_spent',
    'payload',
])]
class SceneLog extends Model
{
    /** @use HasFactory<SceneLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<Scene, $this>
     */
    public function scene(): BelongsTo
    {
        return $this->belongsTo(Scene::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
            'type' => SceneLogType::class,
            'visibility' => SceneLogVisibility::class,
            'payload' => 'array',
            'willpower_spent' => 'boolean',
            'created_at' => 'datetime',
        ];
    }
}
