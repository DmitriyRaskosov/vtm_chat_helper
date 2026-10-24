<?php

namespace App\Http\Resources;

use App\Models\Character;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Элемент списка персонажей (GET /characters).
 *
 * @mixin Character
 */
class CharacterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $character = $this->resource;

        return [
            'id' => (int) $character->id,
            'canonical_name' => $character->entity === null
                ? ''
                : (string) $character->entity->canonical_name,
            'character_type' => $character->character_type->value,
            'user_id' => $character->user_id === null ? null : (int) $character->user_id,
            'description' => $character->description,
        ];
    }
}
