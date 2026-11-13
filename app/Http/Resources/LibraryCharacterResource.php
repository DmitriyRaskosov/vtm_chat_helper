<?php

namespace App\Http\Resources;

use App\Models\LibraryCharacter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LibraryCharacter
 */
class LibraryCharacterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'name' => $this->name,
            'character_type' => $this->character_type->value,
            'clan_id' => $this->clan_id === null ? null : (int) $this->clan_id,
            'sect_id' => $this->sect_id === null ? null : (int) $this->sect_id,
            'nature' => $this->nature,
            'demeanor' => $this->demeanor,
            'concept' => $this->concept,
            'generation' => $this->generation === null ? null : (int) $this->generation,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
