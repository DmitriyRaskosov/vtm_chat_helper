<?php

namespace App\Http\Resources;

use App\Models\Scene;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Scene
 */
class SceneResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $scene = $this->resource;

        return [
            'id' => $scene->id,
            'game_session_id' => $scene->game_session_id,
            'title' => $scene->title,
            'description' => $scene->description,
            'position' => $scene->position,
            'status' => $scene->status->value,
            'started_at' => $scene->started_at?->toISOString(),
            'ended_at' => $scene->ended_at?->toISOString(),
        ];
    }
}
