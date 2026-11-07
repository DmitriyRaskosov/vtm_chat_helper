<?php

namespace App\Http\Controllers;

use App\Broadcasting\SafeBroadcaster;
use App\Character\CharacterStatsService;
use App\Events\CharacterStatsUpdated;
use App\Http\Requests\UpdateCharacterStatsRequest;
use App\Http\Resources\CharacterStatsResource;
use App\Models\Character;
use App\Models\SceneParticipant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CharacterStatsController extends Controller
{
    public function __construct(
        private CharacterStatsService $stats,
    ) {}

    public function update(UpdateCharacterStatsRequest $request, Character $character): JsonResponse
    {
        $this->authorize('update', $character);

        $saved = $this->stats->replace($character, $request->stats(), $request->sheetData());

        $this->broadcastStatsUpdated($saved, $request);

        return response()->json(
            (new CharacterStatsResource($saved))->toArray($request),
        );
    }

    private function broadcastStatsUpdated(Character $character, Request $request): void
    {
        $statsPayload = (new CharacterStatsResource($character))->toArray($request);
        $sceneIds = SceneParticipant::query()
            ->where('character_id', $character->id)
            ->where('is_current', true)
            ->whereNull('left_at')
            ->pluck('scene_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        foreach ($sceneIds as $sceneId) {
            SafeBroadcaster::send(new CharacterStatsUpdated(
                $sceneId,
                (int) $character->id,
                $statsPayload,
            ));
        }
    }
}
