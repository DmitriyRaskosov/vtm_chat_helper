<?php

namespace App\Llm;

use App\Context\ContextBuilder;
use App\Llm\Exceptions\LlmResponseInvalidException;
use App\Llm\Exceptions\LlmUnavailableException;
use App\Models\Scene;

class NpcCopilotService
{
    public function __construct(
        private ChatProvider $chat,
        private ContextBuilder $contextBuilder,
    ) {}

    public function drafts(
        string $npcName,
        string $prompt,
        int $sceneId,
        int $storytellerId,
        ?int $characterId = null,
    ): CopilotDraftResult {
        $draftCount = (int) config('copilot.draft_count');
        $scene = Scene::query()->findOrFail($sceneId);
        $topicsContext = $this->contextBuilder->buildTopics(
            $npcName,
            $prompt,
            $sceneId,
            $draftCount,
            $storytellerId,
            (int) $scene->game_session_id,
            $characterId,
        );

        try {
            $topicTurn = $this->chat->chatTurn($topicsContext->messages, [
                'num_predict' => (int) config('copilot.topic_max_output_tokens', 384),
                'temperature' => (float) config('copilot.topic_temperature', 0.1),
                'json_object' => true,
            ]);
        } catch (\Throwable $e) {
            throw new LlmUnavailableException($e);
        }

        $topics = $this->parseTopics($topicTurn->content, $prompt);
        $context = $this->contextBuilder->buildReply(
            $npcName,
            $prompt,
            $sceneId,
            $draftCount,
            $storytellerId,
            (int) $scene->game_session_id,
            $characterId,
            $topics,
        );
        $messages = $context->messages;
        $metadata = $context->metadata;
        try {
            $replyOptions = [
                'max_tokens' => (int) config('llm.max_output_tokens'),
                'json_object' => true,
            ];
            $turn = $this->chat->chatTurn($messages, $replyOptions);
            $raw = $turn->content;
        } catch (\Throwable $e) {
            throw new LlmUnavailableException($e);
        }

        $metadata['topics'] = $topics;
        $metadata['topic_pass'] = [
            'prompt_version' => $topicsContext->metadata['prompt_version'],
            'input_token_budget' => $topicsContext->metadata['input_token_budget'],
            'input_token_estimate' => $topicsContext->metadata['input_token_estimate'],
            'history_limit' => $topicsContext->metadata['history_limit'],
            'max_output_tokens' => $topicsContext->metadata['max_output_tokens'],
            'included_raw_message_ids' => $topicsContext->metadata['included_raw_message_ids'],
            'excluded_raw_message_count' => $topicsContext->metadata['excluded_raw_message_count'],
        ];

        return new CopilotDraftResult(
            $this->parseDrafts($raw, $draftCount),
            $metadata,
            (string) config('llm.deepseek.chat_model'),
            ContextBuilder::VERSION,
            ContextBuilder::PROMPT_VERSION,
        );
    }

    /**
     * @return list<string>
     */
    private function parseTopics(string $raw, string $prompt): array
    {
        $maxCount = max(1, (int) config('copilot.topic_max_count', 8));
        $json = $this->extractJson($raw);

        if ($json !== null) {
            $decoded = json_decode($json, true);

            if (is_array($decoded) && isset($decoded['topics']) && is_array($decoded['topics'])) {
                $topics = [];

                foreach ($decoded['topics'] as $item) {
                    if (! is_string($item)) {
                        continue;
                    }
                    $topic = $this->normalizeTopic($item);
                    if ($topic !== null) {
                        $topics[] = $topic;
                    }
                }

                $topics = array_values(array_unique($topics));

                if ($topics !== []) {
                    return array_slice($topics, 0, $maxCount);
                }
            }
        }

        $fallback = trim($prompt);

        return $fallback !== '' ? [$fallback] : ['conversation'];
    }

    private function normalizeTopic(string $topic): ?string
    {
        $topic = trim($topic);
        if ($topic === '') {
            return null;
        }
        if (mb_strlen($topic) > 120) {
            $topic = rtrim(mb_substr($topic, 0, 120));
        }

        return $topic === '' ? null : $topic;
    }

    /**
     * @return list<string>
     */
    private function parseDrafts(string $raw, int $expected): array
    {
        $json = $this->extractJson($raw);

        if ($json !== null) {
            $decoded = json_decode($json, true);

            if (is_array($decoded) && isset($decoded['drafts']) && is_array($decoded['drafts'])) {
                $drafts = $this->normalizeDraftItems($decoded['drafts']);

                if (count($drafts) >= $expected) {
                    return array_slice($drafts, 0, $expected);
                }

                if ($drafts !== []) {
                    return $drafts;
                }
            }

            $recovered = $this->extractDraftStringsFromMalformedJson($json);
            if ($recovered !== []) {
                if (count($recovered) >= $expected) {
                    return array_slice($recovered, 0, $expected);
                }

                return $recovered;
            }
        }

        if (preg_match_all('/^\s*(?:\d+[\).\]]\s*|-\s*)(.+)$/m', $raw, $matches)) {
            $drafts = array_values(array_unique(
                array_filter(array_map(trim(...), $matches[1]), fn (string $s): bool => $s !== ''),
            ));

            if (count($drafts) >= $expected) {
                return array_slice($drafts, 0, $expected);
            }

            if ($drafts !== []) {
                return $drafts;
            }
        }

        $trimmed = trim($raw);
        if ($trimmed !== '') {
            return [$trimmed];   // ← одиночный fallback, БЕЗ дублей
        }

        throw new LlmResponseInvalidException;
    }

    /**
     * @param  list<mixed>  $items
     * @return list<string>
     */
    private function normalizeDraftItems(array $items): array
    {
        $drafts = [];

        foreach ($items as $item) {
            if (! is_string($item)) {
                continue;
            }
            $item = trim($item);

            // Вложенный JSON: модель иногда оборачивает черновик в JSON-строку
            if (str_starts_with($item, '{')) {
                $inner = json_decode($item, true);
                if (is_array($inner) && isset($inner['drafts']) && is_array($inner['drafts'])) {
                    foreach ($inner['drafts'] as $sub) {
                        if (is_string($sub) && trim($sub) !== '') {
                            $drafts[] = trim($sub);
                        }
                    }

                    continue;
                }
            }

            if ($item !== '') {
                $drafts[] = $item;
            }
        }

        return array_values(array_unique($drafts));
    }

    /**
     * @return list<string>
     */
    private function extractDraftStringsFromMalformedJson(string $json): array
    {
        if (! preg_match('/"drafts"\s*:\s*\[(.*)/s', $json, $matches)) {
            return [];
        }

        $tail = $matches[1];
        if (! preg_match_all('/"((?:[^"\\\\]|\\\\.)*)"/s', $tail, $stringMatches)) {
            return [];
        }

        $drafts = [];

        foreach ($stringMatches[1] as $fragment) {
            $decoded = json_decode(json_encode($fragment, JSON_UNESCAPED_UNICODE));
            if (is_string($decoded)) {
                $decoded = trim($decoded);
                if ($decoded !== '') {
                    $drafts[] = $decoded;
                }

                continue;
            }

            $fallback = trim(stripcslashes($fragment));
            if ($fallback !== '') {
                $drafts[] = $fallback;
            }
        }

        return array_values(array_unique($drafts));
    }

    private function extractJson(string $raw): ?string
    {
        $trimmed = trim($raw);

        if (str_starts_with($trimmed, '{')) {
            return $trimmed;
        }

        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $raw, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
