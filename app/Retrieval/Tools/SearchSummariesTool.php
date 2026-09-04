<?php

namespace App\Retrieval\Tools;

use App\Enums\RagSourceType;
use App\Rag\RagSearcher;
use App\Retrieval\RetrievalScope;
use App\Retrieval\TextClipper;
use App\Retrieval\ToolResult;

class SearchSummariesTool implements RetrievalTool
{
    public function __construct(private RagSearcher $searcher) {}

    public function name(): string
    {
        return 'search_summaries';
    }

    public function description(): string
    {
        return 'Semantic search over world summaries (L0/L1/scene/session) in the current game session.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'What to look for in summaries.',
                ],
                'scene_id' => [
                    'type' => 'integer',
                    'description' => 'Optional scene in the current game session. Omit to search the whole session.',
                ],
            ],
            'required' => ['query'],
        ];
    }

    public function invoke(RetrievalScope $scope, array $arguments): ToolResult
    {
        $query = trim((string) ($arguments['query'] ?? ''));
        if ($query === '') {
            return ToolResult::error('query is required.');
        }

        $requestedScene = isset($arguments['scene_id']) ? (int) $arguments['scene_id'] : null;
        if (isset($arguments['scene_id']) && $scope->sceneIdInSession($requestedScene) === null) {
            return ToolResult::error('scene_id is outside the current game session.');
        }

        $limit = (int) config('copilot.tools.search_limit', 5);
        $filters = ['game_session_id' => $scope->gameSessionId];
        if ($requestedScene !== null) {
            $filters['scene_id'] = $requestedScene;
        }

        $chunks = $this->searcher->search($query, $limit, [RagSourceType::Summary], $filters);
        $maxChars = (int) config('copilot.tools.max_item_characters', 400);

        $items = $chunks->map(fn ($chunk): array => [
            'summary_id' => (int) $chunk->source_id,
            'level' => $chunk->metadata['level'] ?? $chunk->title,
            'scene_id' => $chunk->metadata['scene_id'] ?? null,
            'content' => TextClipper::clip((string) $chunk->content, $maxChars),
        ])->values()->all();

        return new ToolResult(true, $items, false);
    }
}
