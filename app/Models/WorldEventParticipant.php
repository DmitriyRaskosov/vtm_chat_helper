<?php

namespace App\Models;

use App\Enums\WorldEventParticipantRole;
use Database\Factories\WorldEventParticipantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'event_id',
    'entity_id',
    'participant_role',
    'note',
])]
class WorldEventParticipant extends Model
{
    /** @use HasFactory<WorldEventParticipantFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<WorldEvent, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(WorldEvent::class, 'event_id');
    }

    /**
     * @return BelongsTo<WorldEntity, $this>
     */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(WorldEntity::class, 'entity_id');
    }

    protected function casts(): array
    {
        return [
            'event_id' => 'integer',
            'entity_id' => 'integer',
            'participant_role' => WorldEventParticipantRole::class,
        ];
    }
}
