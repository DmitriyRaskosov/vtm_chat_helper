<?php

namespace App\Http\Controllers;

use App\Character\CharacterSceneGuard;
use App\Http\Requests\CopilotDraftsRequest;
use App\Llm\NpcCopilotService;
use App\Models\Character;
use App\Models\CopilotRequest;
use App\Models\Scene;
use App\Scene\ActiveSceneResolver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class CopilotController extends Controller
{
    public function __construct(
        private ActiveSceneResolver $activeSceneResolver,
        private CharacterSceneGuard $characterSceneGuard,
    ) {}

    public function drafts(CopilotDraftsRequest $request, NpcCopilotService $copilot): JsonResponse
    {
        $sceneId = $request->validated('scene_id') ?? null;
        $scene = $this->activeSceneResolver->resolveActive(
            $sceneId === null ? null : (int) $sceneId,
            forCopilot: true,
        );

        $scene->loadMissing('gameSession');
        [$characterId, $npcName] = $this->resolveNpcIdentity($request, $scene);

        try {
            $result = $copilot->drafts(
                $npcName,
                (string) $request->validated('prompt'),
                $scene->id,
                (int) $request->user()->id,
                $characterId,
            );
        } catch (ConnectionException|RequestException) {
            return response()->json(['message' => 'LLM provider is unavailable.'], 503);
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'LLM provider is unavailable.') {
                return response()->json(['message' => 'LLM provider is unavailable.'], 503);
            }

            return response()->json(['message' => $e->getMessage()], 502);
        }

        $copilotRequest = CopilotRequest::query()->create([
            'scene_id' => $scene->id,
            'storyteller_id' => $request->user()->id,
            'npc_name' => $npcName,
            'character_id' => $characterId,
            'prompt' => $request->validated('prompt'),
            'drafts' => $result->drafts,
            'context_metadata' => $result->contextMetadata,
            'model' => $result->model,
            'prompt_version' => $result->promptVersion,
            'builder_version' => $result->builderVersion,
        ]);

        return response()->json([
            'copilot_request_id' => $copilotRequest->id,
            'drafts' => $result->drafts,
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
