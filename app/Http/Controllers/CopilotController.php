<?php

namespace App\Http\Controllers;

use App\Character\CharacterSceneGuard;
use App\Http\Requests\CopilotDraftsRequest;
use App\Llm\CopilotDraftService;
use App\Models\Character;
use App\Models\Scene;
use App\Scene\ActiveSceneResolver;
use Illuminate\Http\JsonResponse;

class CopilotController extends Controller
{
    public function __construct(
        private ActiveSceneResolver $activeSceneResolver,
        private CharacterSceneGuard $characterSceneGuard,
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
        [$characterId, $npcName] = $this->resolveNpcIdentity($request, $scene);

        $created = $this->copilotDrafts->create(
            $npcName,
            (string) $request->validated('prompt'),
            $scene->id,
            (int) $request->user()->id,
            $characterId,
        );

        return response()->json([
            'copilot_request_id' => $created->copilotRequestId,
            'drafts' => $created->drafts,
        ]);
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function resolveNpcIdentity(CopilotDraftsRequest $request, Scene $scene): array
    {
        $characterId = (int) $request->validated('character_id');
        $character = Character::query()->findOrFail((int) $characterId);
        $this->characterSceneGuard->assertUsableIn($character, $scene);

        $character->loadMissing('entity');
        $npcName = $character->entity?->canonical_name;
        abort_if(! is_string($npcName) || $npcName === '', 422, 'NPC name is required.');

        return [$character->id, $npcName];
    }
}
