<?php

namespace App\Http\Resources;

use App\Enums\SceneStatus;
use App\Models\GameSession;
use App\Models\Scene;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin GameSession
 */
class GameSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $gameSession = $this->resource;

        return [
            'id' => $gameSession->id,
            'chronicle_id' => $gameSession->chronicle_id,
            'title' => $gameSession->title,
            'status' => $gameSession->status->value,
            'active_scene_id' => $gameSession->scenes
                ->firstWhere('status', SceneStatus::Active)?->id,
            'scenes' => $gameSession->scenes
                ->map(fn (Scene $scene): array => [
                    'id' => $scene->id,
                    'title' => $scene->title,
                    'description' => $scene->description,
                    'position' => $scene->position,
                    'status' => $scene->status->value,
                    'started_at' => $scene->started_at?->toISOString(),
                    'ended_at' => $scene->ended_at?->toISOString(),
                ])
                ->values(),
        ];
    }
}
