<?php

namespace App\Retrieval\Tools;

use App\Models\Message;
use App\Retrieval\RetrievalScope;
use App\Retrieval\TextClipper;
use App\Retrieval\ToolResult;

class GetMessageRangeTool implements RetrievalTool
{
    public function name(): string
    {
        return 'get_message_range';
    }

    public function description(): string
    {
        return 'Load a short contiguous range of messages by ID in the current game session.';
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'from_id' => [
                    'type' => 'integer',
                    'description' => 'First message ID, inclusive.',
                ],
                'to_id' => [
                    'type' => 'integer',
                    'description' => 'Last message ID, inclusive.',
                ],
                'scene_id' => [
                    'type' => 'integer',
                    'description' => 'Optional scene in the current game session.',
                ],
            ],
            'required' => ['from_id', 'to_id'],
        ];
    }

    public function invoke(RetrievalScope $scope, array $arguments): ToolResult
    {
        $fromId = (int) ($arguments['from_id'] ?? 0);
        $toId = (int) ($arguments['to_id'] ?? 0);
        if ($fromId < 1 || $toId < 1) {
            return ToolResult::error('from_id and to_id must be positive.');
        }

        if ($fromId > $toId) {
            [$fromId, $toId] = [$toId, $fromId];
        }

        $requestedScene = isset($arguments['scene_id']) ? (int) $arguments['scene_id'] : null;
        if (isset($arguments['scene_id']) && $scope->sceneIdInSession($requestedScene) === null) {
            return ToolResult::error('scene_id is outside the current game session.');
        }

        $limit = (int) config('copilot.tools.range_limit', 20);
        $maxChars = (int) config('copilot.tools.max_item_characters', 400);

        $query = Message::query()
            ->with('user:id,name')
            ->whereHas('scene', fn ($builder) => $builder->where('game_session_id', $scope->gameSessionId))
            ->where('id', '>=', $fromId)
            ->where('id', '<=', $toId)
            ->when($requestedScene !== null, fn ($builder) => $builder->where('scene_id', $requestedScene))
            ->orderBy('id')
            ->limit($limit + 1);

        $messages = $query->get();
        $truncated = $messages->count() > $limit;
        $messages = $messages->take($limit);

        $items = $messages->map(fn (Message $message): array => [
            'message_id' => $message->id,
            'scene_id' => $message->scene_id,
            'author' => $message->displayAuthor(),
            'content' => TextClipper::clip($message->body, $maxChars),
        ])->values()->all();

        return new ToolResult(true, $items, $truncated);
    }
}
