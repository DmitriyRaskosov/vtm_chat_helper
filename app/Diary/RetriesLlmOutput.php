<?php

namespace App\Diary;

use App\Diary\Data\DiaryLlmCallResult;
use App\Llm\ChatProvider;
use Illuminate\Support\Facades\Log;

trait RetriesLlmOutput
{
    /**
     * @param  list<array<string, mixed>>  $messages
     * @param  array<string, int|float|string|bool>  $options
     */
    protected function callWithRetry(
        ChatProvider $chat,
        array $messages,
        array $options,
        ?int $npcId = null,
        ?int $sceneId = null,
    ): DiaryLlmCallResult {
        $turn = $chat->chatTurn($messages, $options);
        $content = trim($turn->content);

        if ($turn->finishReason !== 'length') {
            return new DiaryLlmCallResult($content, false);
        }

        $messages[] = ['role' => 'assistant', 'content' => $turn->content];
        $tail = mb_substr($content, -200);
        $messages[] = [
            'role' => 'user',
            'content' => "Продолжи текст с этого места. Выведи только продолжение.\n\n{$tail}",
        ];

        $retryTurn = $chat->chatTurn($messages, $options);
        $merged = $content.trim($retryTurn->content);

        if ($retryTurn->finishReason === 'length') {
            Log::warning('diary.llm.truncated_after_retry', [
                'npc_id' => $npcId,
                'scene_id' => $sceneId,
            ]);

            return new DiaryLlmCallResult($merged, true);
        }

        return new DiaryLlmCallResult($merged, false);
    }
}
