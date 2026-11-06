<?php

namespace App\Http\Resources;

use App\Models\Character;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Карточка персонажа в личном кабинете.
 *
 * @mixin Character
 */
class ProfileCharacterResource extends JsonResource
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
            'avatar_path' => $character->avatar_path,
            'chronicle_id' => (int) $character->chronicle_id,
            'chronicle_title' => (string) $character->chronicle->title,
            'is_active' => (bool) $character->is_active,
            'updated_at' => $character->updated_at?->getTimestamp(),
        ];
    }
}
