<?php

namespace App\Context;

use App\Models\CopilotRequest;
use App\Models\StorytellerIntentSummary;
use Illuminate\Support\Facades\Log;
use Throwable;

class StorytellerIntentManager
{
    public function __construct(private StorytellerIntentGenerator $generator) {}

    public function refresh(int $gameSessionId, int $storytellerId): void
    {
        $limit = (int) config('context.intent.request_limit', 20);
        $requests = CopilotRequest::query()
            ->with('scene:id,title')
            ->where('storyteller_id', $storytellerId)
            ->whereHas('scene', fn ($query) => $query->where('game_session_id', $gameSessionId))
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();

        if ($requests->isEmpty()) {
            return;
        }

        $requestIds = $requests->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $sourceHash = hash('sha256', $gameSessionId.'|'.$storytellerId.'|'.implode(',', $requestIds));

        if (StorytellerIntentSummary::query()->where('source_hash', $sourceHash)->exists()) {
            return;
        }

        $latest = StorytellerIntentSummary::query()
            ->where('game_session_id', $gameSessionId)
            ->where('storyteller_id', $storytellerId)
            ->orderByDesc('id')
            ->first();

        $sourceTexts = $requests->map(function (CopilotRequest $request): string {
            $sceneTitle = $request->scene?->title ?? (string) $request->scene_id;

            return "Scene: {$sceneTitle}\nNPC: {$request->npc_name}\nIntent: {$request->prompt}";
        })->all();

        try {
            $content = $this->generator->generate($latest?->content, $sourceTexts);
        } catch (Throwable $e) {
            Log::warning('Storyteller intent summary failed.', [
                'game_session_id' => $gameSessionId,
                'storyteller_id' => $storytellerId,
                'message' => $e->getMessage(),
            ]);

            return;
        }

        StorytellerIntentSummary::query()->create([
            'game_session_id' => $gameSessionId,
            'storyteller_id' => $storytellerId,
            'content' => $content,
            'first_copilot_request_id' => $requests->first()->id,
            'last_copilot_request_id' => $requests->last()->id,
            'request_count' => $requests->count(),
            'model' => (string) config('ollama.chat_model'),
            'prompt_version' => StorytellerIntentGenerator::PROMPT_VERSION,
            'source_hash' => $sourceHash,
        ]);
    }

    public function latestFor(int $gameSessionId, int $storytellerId): ?StorytellerIntentSummary
    {
        return StorytellerIntentSummary::query()
            ->where('game_session_id', $gameSessionId)
            ->where('storyteller_id', $storytellerId)
            ->orderByDesc('id')
            ->first();
    }
}
