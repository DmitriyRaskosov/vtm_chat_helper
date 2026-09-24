<?php

namespace App\Llm;

use App\Context\ContextBuilder;
use App\Models\Scene;
use RuntimeException;

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
                'temperature' => (float) config('copilot.topic_temperature', 0.2),
            ], []);
        } catch (\Throwable $e) {
            throw new RuntimeException('LLM provider is unavailable.', 0, $e);
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
            ];
            $turn = $this->chat->chatTurn($messages, $replyOptions, []);
            $raw = $turn->content;
        } catch (\Throwable $e) {
            throw new RuntimeException('LLM provider is unavailable.', 0, $e);
        }

        $metadata['topics'] = $topics;
        $metadata['topic_pass'] = [
            'prompt_version' => $topicsContext->metadata['prompt_version'],
            'input_token_budget' => $topicsContext->metadata['input_token_budget'],
            'input_token_estimate' => $topicsContext->metadata['input_token_estimate'],
            'history_limit' => $topicsContext->metadata['history_limit'],
            'ollama_max_output_tokens' => $topicsContext->metadata['ollama_max_output_tokens'],
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

    private function resolveModelName(): string
    {
        return config('llm.driver') === 'deepseek'
            ? (string) config('llm.deepseek.chat_model')
            : throw new RuntimeException('Unknown LLM driver: '.config('llm.driver'));
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
                $drafts = array_values(array_filter(
                    array_map(fn ($item): string => is_string($item) ? trim($item) : '', $decoded['drafts']),
                    fn (string $item): bool => $item !== '',
                ));

                if (count($drafts) >= $expected) {
                    return array_slice($drafts, 0, $expected);
                }
            }
        }

        if (preg_match_all('/^\s*(?:\d+[\).\]]\s*|-\s*)(.+)$/m', $raw, $matches)) {
            $drafts = array_values(array_filter(array_map(trim(...), $matches[1]), fn (string $s): bool => $s !== ''));

            if (count($drafts) >= $expected) {
                return array_slice($drafts, 0, $expected);
            }
        }

        $trimmed = trim($raw);
        if ($trimmed !== '') {
            return array_fill(0, $expected, $trimmed);
        }

        throw new RuntimeException('Could not parse draft replies from the model response.');
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
