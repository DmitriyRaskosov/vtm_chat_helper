<?php

namespace App\Models;

use App\Enums\CombatEncounterStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property CombatEncounterStatus $status
 * @property list<string>|null $stat_keys
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 */
#[Fillable([
    'scene_id',
    'status',
    'current_round',
    'current_turn_index',
    'stat_keys',
    'started_at',
    'ended_at',
])]
class CombatEncounter extends Model
{
    /**
     * @param  Builder<CombatEncounter>  $query
     * @return Builder<CombatEncounter>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', CombatEncounterStatus::Active);
    }

    /**
     * @return BelongsTo<Scene, $this>
     */
    public function scene(): BelongsTo
    {
        return $this->belongsTo(Scene::class);
    }

    /**
     * @return HasMany<CombatParticipant, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(CombatParticipant::class);
    }

    protected function casts(): array
    {
        return [
            'scene_id' => 'integer',
            'status' => CombatEncounterStatus::class,
            'current_round' => 'integer',
            'current_turn_index' => 'integer',
            'stat_keys' => 'array',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }
}
