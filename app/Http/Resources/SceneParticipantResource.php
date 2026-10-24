<?php

namespace App\Http\Resources;

use App\Enums\CharacterType;
use App\Models\Character;
use App\Models\SceneParticipant;
use App\Scene\SceneParticipantService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SceneParticipant
 */
class SceneParticipantResource extends JsonResource
{
    public function __construct(
        SceneParticipant $resource,
        private readonly ?Character $character,
        private readonly SceneParticipantService $participants,
    ) {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $participant = $this->resource;
        $character = $this->character;
        if ($character === null) {
            $character = $this->participants->characterForSerialization($participant);
        }

        $name = $character?->entity?->canonical_name;
        $characterType = $character?->character_type;

        return [
            'id' => (int) $participant->id,
            'scene_id' => (int) $participant->scene_id,
            'character_id' => (int) $participant->character_id,
            'character_name' => is_string($name) && $name !== '' ? $name : null,
            'character_type' => $characterType instanceof CharacterType ? $characterType->value : $characterType,
            'character_description' => is_string($character?->description) && trim($character->description) !== ''
                ? trim($character->description)
                : null,
            'user_id' => $character?->user_id === null ? null : (int) $character->user_id,
            'role' => $participant->role->value,
            'visible' => (bool) $participant->visible,
            'is_current' => (bool) $participant->is_current,
            'entered_at' => $participant->entered_at?->toISOString(),
            'left_at' => $participant->left_at?->toISOString(),
        ];
    }
}
