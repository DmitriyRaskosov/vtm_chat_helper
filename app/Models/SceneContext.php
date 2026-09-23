<?php

namespace App\Models;

use Database\Factories\SceneContextFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'scene_id',
    'chronicle_id',
    'location_entity_id',
    'atmosphere',
    'situation',
    'storyteller_notes',
    'revision',
    'frozen_revision',
    'updated_by',
])]
class SceneContext extends Model
{
    /** @use HasFactory<SceneContextFactory> */
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
     * Typed location row. Inverse WorldEntity belongsTo on shared PK is omitted.
     *
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_entity_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    protected function casts(): array
    {
        return [
            'scene_id' => 'integer',
            'chronicle_id' => 'integer',
            'location_entity_id' => 'integer',
            'revision' => 'integer',
            'frozen_revision' => 'integer',
            'updated_by' => 'integer',
        ];
    }
}
