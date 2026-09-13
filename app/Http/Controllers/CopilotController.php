<?php

namespace App\Http\Controllers;

use App\Enums\SceneStatus;
use App\Http\Requests\CopilotDraftsRequest;
use App\Llm\NpcCopilotService;
use App\Models\Character;
use App\Models\Chronicle;
use App\Models\CopilotRequest;
use App\Models\Scene;
use App\Models\WorldEntity;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class CopilotController extends Controller
{
    public function drafts(CopilotDraftsRequest $request, NpcCopilotService $copilot): JsonResponse
    {
        $sceneId = $request->validated('scene_id') ?? null;
        $chronicleId = $request->validated('chronicle_id') ?? null;

        if ($sceneId === null) {
            $chronicleId = Chronicle::resolveId($chronicleId === null ? null : (int) $chronicleId);
        }

        $scene = Scene::query()
            ->when($sceneId !== null, fn ($query) => $query->whereKey((int) $sceneId))
            ->when($sceneId === null, fn ($query) => $query->active())
            ->whereHas('gameSession', function ($query) use ($chronicleId) {
                $query->active();

                if ($chronicleId !== null) {
                    $query->where('chronicle_id', (int) $chronicleId);
                }
            })
            ->first();

        abort_if(
            $scene === null || $scene->status !== SceneStatus::Active,
            409,
            'Copilot requires an active scene.',
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
            return response()->json(['message' => 'Ollama is unavailable.'], 503);
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'Ollama is unavailable.') {
                return response()->json(['message' => 'Ollama is unavailable.'], 503);
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
        abort_if(
            (int) $character->chronicle_id !== (int) $scene->gameSession->chronicle_id,
            409,
            'Character does not belong to this chronicle.',
        );
        abort_if(! $character->is_active, 409, 'Character is archived.');

        $npcName = WorldEntity::query()->whereKey($character->id)->value('canonical_name');
        abort_if(! is_string($npcName) || $npcName === '', 422, 'NPC name is required.');

        return [$character->id, $npcName];
    }
}
