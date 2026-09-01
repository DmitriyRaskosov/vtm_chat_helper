<?php

namespace App\Models;

use App\Enums\GameSessionStatus;
use Database\Factories\GameSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['title', 'status', 'created_by', 'activated_at'])]
class GameSession extends Model
{
    /** @use HasFactory<GameSessionFactory> */
    use HasFactory;

    /**
     * @param  Builder<GameSession>  $query
     * @return Builder<GameSession>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', GameSessionStatus::Active);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<Scene, $this>
     */
    public function scenes(): HasMany
    {
        return $this->hasMany(Scene::class)->orderBy('position');
    }

    protected function casts(): array
    {
        return [
            'status' => GameSessionStatus::class,
            'activated_at' => 'datetime',
        ];
    }
}
