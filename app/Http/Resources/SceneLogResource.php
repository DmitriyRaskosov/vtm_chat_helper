<?php

namespace App\Http\Resources;

use App\Models\SceneLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SceneLog
 */
class SceneLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $log = $this->resource;

        return [
            'id' => $log->id,
            'scene_id' => $log->scene_id,
            'character_id' => $log->character_id,
            'type' => $log->type->value,
            'visibility' => $log->visibility->value,
            'message' => $log->message,
            'comment' => $log->comment,
            'willpower_spent' => $log->willpower_spent,
            'payload' => $log->payload,
            'created_at' => $log->created_at?->timezone(config('app.timezone'))->format('H:i'),
        ];
    }
}
