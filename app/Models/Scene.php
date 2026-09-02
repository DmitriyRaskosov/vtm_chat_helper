<?php

namespace App\Models;

use App\Enums\SceneStatus;
use Database\Factories\SceneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'game_session_id',
    'position',
    'title',
    'description',
    'status',
    'started_at',
    'ended_at',
])]
class Scene extends Model
{
    /** @use HasFactory<SceneFactory> */
    use HasFactory;

    /**
     * @param  Builder<Scene>  $query
     * @return Builder<Scene>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', SceneStatus::Active);
    }

    /**
     * @return BelongsTo<GameSession, $this>
     */
    public function gameSession(): BelongsTo
    {
        return $this->belongsTo(GameSession::class);
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * @return HasMany<CopilotRequest, $this>
     */
    public function copilotRequests(): HasMany
    {
        return $this->hasMany(CopilotRequest::class);
    }

    /**
     * @return HasMany<ContextSummary, $this>
     */
    public function contextSummaries(): HasMany
    {
        return $this->hasMany(ContextSummary::class);
    }

    /**
     * @return HasOne<SceneContextState, $this>
     */
    public function contextState(): HasOne
    {
        return $this->hasOne(SceneContextState::class);
    }

    protected function casts(): array
    {
        return [
            'status' => SceneStatus::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(fn (Scene $scene) => $scene->contextState()->create());
    }
}
