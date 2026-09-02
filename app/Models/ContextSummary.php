<?php

namespace App\Models;

use App\Enums\ContextSummaryLevel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable([
    'game_session_id',
    'scene_id',
    'level',
    'first_message_id',
    'last_message_id',
    'content',
    'metadata',
    'model',
    'prompt_version',
    'source_hash',
])]
class ContextSummary extends Model
{
    /**
     * @return BelongsTo<GameSession, $this>
     */
    public function gameSession(): BelongsTo
    {
        return $this->belongsTo(GameSession::class);
    }

    /**
     * @return BelongsTo<Scene, $this>
     */
    public function scene(): BelongsTo
    {
        return $this->belongsTo(Scene::class);
    }

    /**
     * @return HasMany<ContextSummarySource, $this>
     */
    public function sources(): HasMany
    {
        return $this->hasMany(ContextSummarySource::class)->orderBy('position');
    }

    protected function casts(): array
    {
        return [
            'level' => ContextSummaryLevel::class,
            'metadata' => 'array',
            'first_message_id' => 'integer',
            'last_message_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Context summaries are immutable.'));
    }
}
