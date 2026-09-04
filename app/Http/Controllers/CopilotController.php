<?php

namespace App\Http\Controllers;

use App\Enums\SceneStatus;
use App\Http\Requests\CopilotDraftsRequest;
use App\Jobs\RefreshStorytellerIntentJob;
use App\Llm\NpcCopilotService;
use App\Models\CopilotRequest;
use App\Models\Scene;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class CopilotController extends Controller
{
    public function drafts(CopilotDraftsRequest $request, NpcCopilotService $copilot): JsonResponse
    {
        $sceneId = $request->validated('scene_id');
        $scene = Scene::query()
            ->when($sceneId !== null, fn ($query) => $query->whereKey((int) $sceneId))
            ->when($sceneId === null, fn ($query) => $query->active())
            ->whereHas('gameSession', fn ($query) => $query->active())
            ->first();

        abort_if(
            $scene === null || $scene->status !== SceneStatus::Active,
            409,
            'Copilot requires an active scene.',
        );

        try {
            $result = $copilot->drafts(
                (string) $request->validated('npc_name'),
                (string) $request->validated('prompt'),
                $scene->id,
                (int) $request->user()->id,
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
            'npc_name' => $request->validated('npc_name'),
            'prompt' => $request->validated('prompt'),
            'drafts' => $result->drafts,
            'context_metadata' => $result->contextMetadata,
            'model' => $result->model,
            'prompt_version' => $result->promptVersion,
            'builder_version' => $result->builderVersion,
        ]);

        RefreshStorytellerIntentJob::dispatch(
            (int) $scene->game_session_id,
            (int) $request->user()->id,
        );

        return response()->json([
            'copilot_request_id' => $copilotRequest->id,
            'drafts' => $result->drafts,
        ]);
    }
}
