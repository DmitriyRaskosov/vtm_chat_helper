<?php

namespace App\Http\Resources;

use App\Models\Character;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Сохранённые статы листа (PATCH /characters/{character}/stats).
 *
 * @mixin Character
 */
class CharacterStatsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $stats = $this->resource->stats;

        return [
            'stats' => is_array($stats) ? $stats : [],
        ];
    }
}
