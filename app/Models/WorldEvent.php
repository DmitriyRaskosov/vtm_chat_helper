<?php

namespace App\Models;

use App\Enums\WorldEntityType;
use App\Enums\WorldEventStatus;
use App\Enums\WorldEventType;
use App\Enums\WorldEventVisibility;
use Database\Factories\WorldEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'id',
    'chronicle_id',
    'entity_type',
    'scene_id',
    'title',
    'description',
    'event_type',
    'status',
    'importance',
    'visibility',
    'approved_at',
    'approved_by',
])]
class WorldEvent extends Model
{
    /** @use HasFactory<WorldEventFactory> */
    use HasFactory;

    public $incrementing = false;

    /**
     * Shared-PK identity is read from WorldEntity::event(). Inverse belongsTo
     * on the same `id` is omitted: Eloquent recurses until PHP dies.
     *
     * @return BelongsTo<Chronicle, $this>
     */
    public function chronicle(): BelongsTo
    {
        return $this->belongsTo(Chronicle::class);
    }

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
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<WorldEventParticipant, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(WorldEventParticipant::class, 'event_id');
    }

    /**
     * @return HasMany<WorldEventSource, $this>
     */
    public function sources(): HasMany
    {
        return $this->hasMany(WorldEventSource::class, 'event_id');
    }

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'chronicle_id' => 'integer',
            'entity_type' => WorldEntityType::class,
            'scene_id' => 'integer',
            'event_type' => WorldEventType::class,
            'status' => WorldEventStatus::class,
            'importance' => 'integer',
            'visibility' => WorldEventVisibility::class,
            'approved_at' => 'datetime',
            'approved_by' => 'integer',
        ];
    }
}
