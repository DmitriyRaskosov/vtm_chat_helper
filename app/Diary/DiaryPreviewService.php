<?php

namespace App\Diary;

use App\Context\DiaryRenderer;
use App\Context\LineTrimmer;
use App\Context\TokenEstimator;
use App\Models\Character;
use App\Models\Message;
use Illuminate\Support\Collection;

final class DiaryPreviewService
{
    public function __construct(
        private DiaryRetrievalService $retrieval,
        private LineTrimmer $trimmer,
        private TokenEstimator $estimator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function preview(Character $npc, ?int $sceneId, string $prompt): array
    {
        $recentFirstMessageId = $sceneId === null
            ? null
            : $this->resolveRecentFirstMessageId($sceneId);

        $bundle = $this->retrieval->retrieveWithDiagnostics(
            $npc,
            $prompt,
            limit: DiaryRetrievalService::MAX_ENTRIES,
            recentFirstMessageId: $recentFirstMessageId,
            currentSceneId: $sceneId,
        );

        $budget = (int) config('context.assembler.sections.diary.max', 1200);
        $built = DiaryRenderer::buildLines($bundle['selected']);
        [$content, $truncated] = $this->trimmer->prefix('## Diary', $built['lines'], $budget);
        $tokens = $content === '' ? 0 : $this->estimator->estimate($content);

        return [
            'character_id' => (int) $npc->id,
            'scene_id' => $sceneId,
            'recent_first_message_id' => $recentFirstMessageId,
            'prompt' => $prompt,
            'budget' => $budget,
            'tokens_estimate' => $tokens,
            'truncated' => $truncated,
            'stats' => $bundle['stats'],
            'selected' => $this->serializeEntries($bundle['selected']),
            'rendered' => $content,
        ];
    }

    private function resolveRecentFirstMessageId(int $sceneId): ?int
    {
        $id = Message::query()
            ->where('scene_id', $sceneId)
            ->orderByDesc('id')
            ->limit((int) config('copilot.history_limit', 15))
            ->pluck('id')
            ->min();

        return $id === null ? null : (int) $id;
    }

    /**
     * @param  Collection<int, \App\Models\CharacterDiaryEntry>  $entries
     * @return list<array<string, mixed>>
     */
    private function serializeEntries(Collection $entries): array
    {
        $out = [];
        $isFirst = true;

        foreach ($entries as $e) {
            $out[] = [
                'id' => (int) $e->id,
                'level' => (int) $e->level,
                'scene_id' => $e->scene_id === null ? null : (int) $e->scene_id,
                'from_message_id' => $e->from_message_id === null ? null : (int) $e->from_message_id,
                'to_message_id' => $e->to_message_id === null ? null : (int) $e->to_message_id,
                'created_at' => $e->created_at?->toIso8601String(),
                'label' => DiaryRenderer::labelFor($e, $isFirst),
                'excerpt' => mb_substr(trim((string) $e->entry), 0, 200),
                'importance' => $e->importance === null ? null : (float) $e->importance,
                'emotion' => $e->emotion?->value,
            ];
            $isFirst = false;
        }

        return $out;
    }
}