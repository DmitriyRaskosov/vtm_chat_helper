<?php

namespace App\Http\Controllers;

use App\Character\Exceptions\InvalidCharacterInputException;
use App\Http\Requests\CopilotDraftsRequest;
use App\Http\Resources\CopilotDraftsResource;
use App\Llm\CopilotDraftService;
use App\Models\Character;
use App\Models\Scene;
use App\Scene\ActiveSceneResolver;
use Illuminate\Http\JsonResponse;

class CopilotController extends Controller
{
    public function __construct(
        private ActiveSceneResolver $activeSceneResolver,
        private CopilotDraftService $copilotDrafts,
    ) {}

    public function drafts(CopilotDraftsRequest $request): JsonResponse
    {
        $sceneId = $request->validated('scene_id') ?? null;
        $scene = $this->activeSceneResolver->resolveActive(
            $sceneId === null ? null : (int) $sceneId,
            forCopilot: true,
        );

        $scene->loadMissing('gameSession');
        $this->authorize('createCopilotDraft', $scene);

        [$characterId, $npcName] = $this->resolveNpcIdentity($request, $scene);

        $created = $this->copilotDrafts->create(
            $npcName,
            (string) $request->validated('prompt'),
            $scene->id,
            (int) $request->user()->id,
            $characterId,
        );

        return response()->json((new CopilotDraftsResource($created))->toArray($request));
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function resolveNpcIdentity(CopilotDraftsRequest $request, Scene $scene): array
    {
        $characterId = (int) $request->validated('character_id');
        $character = Character::query()->findOrFail((int) $characterId);
        $this->authorize('viewInScene', [$character, $scene]);

        $character->loadMissing('entity');
        $npcName = $character->entity?->canonical_name;
        if (! is_string($npcName) || $npcName === '') {
            throw new InvalidCharacterInputException('NPC name is required.');
        }

        return [$character->id, $npcName];
    }
}
