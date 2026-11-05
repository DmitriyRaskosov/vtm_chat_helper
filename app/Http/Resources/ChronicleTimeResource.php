<?php

namespace App\Http\Resources;

use App\Chronicle\Data\ChronicleTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ChronicleTime
 */
class ChronicleTimeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ChronicleTime $time */
        $time = $this->resource;

        return [
            'current_in_game_date' => $time->currentInGameDate->toIso8601String(),
            'scene_id' => $time->activeSceneId,
            'started_at_in_game' => $time->startedAtInGame?->toIso8601String(),
        ];
    }
}
