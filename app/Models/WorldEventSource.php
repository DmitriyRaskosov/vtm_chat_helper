<?php

namespace App\Models;

use Database\Factories\WorldEventSourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'event_id',
    'message_id',
    'scene_id',
    'copilot_request_id',
    'excerpt',
])]
class WorldEventSource extends Model
{
    /** @use HasFactory<WorldEventSourceFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<WorldEvent, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(WorldEvent::class, 'event_id');
    }

    /**
     * @return BelongsTo<Message, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * @return BelongsTo<Scene, $this>
     */
    public function scene(): BelongsTo
    {
        return $this->belongsTo(Scene::class);
    }

    /**
     * @return BelongsTo<CopilotRequest, $this>
     */
    public function copilotRequest(): BelongsTo
    {
        return $this->belongsTo(CopilotRequest::class);
    }

    protected function casts(): array
    {
        return [
            'event_id' => 'integer',
            'message_id' => 'integer',
            'scene_id' => 'integer',
            'copilot_request_id' => 'integer',
        ];
    }
}
