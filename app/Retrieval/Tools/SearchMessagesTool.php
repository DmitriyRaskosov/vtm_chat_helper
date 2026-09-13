<?php

namespace App\Retrieval\Tools;

use App\Rag\MessageSearcher;
use App\Retrieval\RetrievalScope;
use App\Retrieval\TextClipper;
use App\Retrieval\ToolResult;

class SearchMessagesTool implements RetrievalTool
{
    public function __construct(private MessageSearcher $searcher) {}

    public function name(): string
    {
        return 'search_messages';
    }

    public function description(): string
    {
        return 'Semantic search over chat messages in the current game session. Never returns the full history.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'What to look for in past messages.',
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
        $hits = $this->searcher->search(
            $scope->chronicleId,
            $query,
            $limit,
            $scope->gameSessionId,
            $requestedScene,
        );
        $maxChars = (int) config('copilot.tools.max_item_characters', 400);

        $items = $hits->map(fn ($hit): array => [
            'message_id' => (int) $hit->message_id,
            'scene_id' => $hit->scene_id,
            'content' => TextClipper::clip((string) $hit->content, $maxChars),
        ])->values()->all();

        return new ToolResult(true, $items, false);
    }
}
