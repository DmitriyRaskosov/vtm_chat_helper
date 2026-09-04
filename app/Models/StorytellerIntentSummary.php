<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'game_session_id',
    'storyteller_id',
    'content',
    'first_copilot_request_id',
    'last_copilot_request_id',
    'request_count',
    'model',
    'prompt_version',
    'source_hash',
])]
class StorytellerIntentSummary extends Model
{
    /**
     * @return BelongsTo<GameSession, $this>
     */
    public function gameSession(): BelongsTo
    {
        return $this->belongsTo(GameSession::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function storyteller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'storyteller_id');
    }

    protected function casts(): array
    {
        return [
            'first_copilot_request_id' => 'integer',
            'last_copilot_request_id' => 'integer',
            'request_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Storyteller intent summaries are immutable.'));
    }
}
