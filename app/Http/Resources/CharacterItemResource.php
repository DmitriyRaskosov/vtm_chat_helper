<?php

namespace App\Http\Resources;

use App\Models\CharacterItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CharacterItem
 */
class CharacterItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'character_id' => (int) $this->character_id,
            'name' => $this->name,
            'description' => $this->description,
            'quantity' => (int) $this->quantity,
            'is_equipped' => (bool) $this->is_equipped,
        ];
    }
}
