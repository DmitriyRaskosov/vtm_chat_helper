<?php

namespace App\Character;

use App\Models\SceneParticipant;
use App\Scene\ActiveSceneResolver;

final class CharacterVisibility
{
    public function __construct(private ActiveSceneResolver $activeSceneResolver) {}

    /**
     * @return list<int>
     */
    public function visibleIdsForPlayer(int $chronicleId, int $pcId): array
    {
        $visibleIds = [$pcId];

        $activeScene = $this->activeSceneResolver->activeOrNull();

        if ($activeScene === null) {
            return $visibleIds;
        }

        $participantIds = SceneParticipant::query()
            ->where('scene_id', $activeScene->id)
            ->where('is_current', true)
            ->pluck('character_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_unique([...$visibleIds, ...$participantIds]));
    }
}
