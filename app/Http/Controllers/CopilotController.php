<?php

namespace App\Http\Controllers;

use App\Http\Requests\CopilotDraftsRequest;
use App\Llm\NpcCopilotService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class CopilotController extends Controller
{
    public function drafts(CopilotDraftsRequest $request, NpcCopilotService $copilot): JsonResponse
    {
        try {
            $drafts = $copilot->drafts(
                (string) $request->validated('npc_name'),
                (string) $request->validated('prompt'),
            );
        } catch (ConnectionException|RequestException) {
            return response()->json(['message' => 'Ollama is unavailable.'], 503);
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'Ollama is unavailable.') {
                return response()->json(['message' => 'Ollama is unavailable.'], 503);
            }

            return response()->json(['message' => $e->getMessage()], 502);
        }

        return response()->json(['drafts' => $drafts]);
    }
}
