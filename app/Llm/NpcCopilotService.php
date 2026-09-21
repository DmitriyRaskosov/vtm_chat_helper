<?php

namespace App\Llm;

use App\Context\ContextBuilder;
use App\Models\Scene;
use App\Retrieval\RetrievalOrchestrator;
use App\Retrieval\RetrievalScope;
use App\Retrieval\Tools\RetrievalToolRegistry;
use RuntimeException;

class NpcCopilotService
{
    public function __construct(
        private ChatProvider $chat,
        private ContextBuilder $contextBuilder,
        private RetrievalToolRegistry $tools,
        private RetrievalOrchestrator $retrieval,
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
        $toolInvocations = [];
        $loopTokens = 0;

        try {
            $raw = $this->completeWithTools($messages, $scene, $toolInvocations, $loopTokens);
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
        $metadata['tool_iterations'] = count(array_unique(array_column($toolInvocations, 'iteration')));
        $metadata['tool_invocations'] = $toolInvocations;
        $metadata['tool_loop_token_estimate'] = $loopTokens;

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
            : (string) config('ollama.chat_model');
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     * @param  list<array<string, mixed>>  $toolInvocations
     */
    private function completeWithTools(array $messages, Scene $scene, array &$toolInvocations, int &$loopTokens): string
    {
        $enabled = (bool) config('copilot.tools.enabled');
        $maxIterations = (int) config('copilot.tools.max_iterations', 2);
        $maxLoopTokens = (int) config('copilot.tools.max_loop_tokens', 2000);
        $definitions = $enabled ? $this->tools->ollamaDefinitions() : [];
        $scope = RetrievalScope::fromScene($scene);
        $iteration = 0;
        $replyOptions = [
            'num_predict' => (int) config('ollama.max_output_tokens'),
        ];

        while (true) {
            $allowTools = $enabled && $iteration < $maxIterations && $loopTokens < $maxLoopTokens;
            $turn = $this->chat->chatTurn($messages, $replyOptions, $allowTools ? $definitions : []);

            if ($turn->toolCalls === []) {
                return $turn->content;
            }

            if (! $allowTools) {
                $messages[] = $turn->toAssistantMessage();
                $messages[] = [
                    'role' => 'user',
                    'content' => 'Stop using tools. Respond with JSON drafts only.',
                ];
                $final = $this->chat->chatTurn($messages, $replyOptions, []);

                return $final->content;
            }

            $iteration++;
            $messages[] = $turn->toAssistantMessage();

            foreach ($turn->toolCalls as $call) {
                $invoked = $this->retrieval->invokeCall($call, $scope);
                $loopTokens += $invoked['token_estimate'];
                $toolInvocations[] = [
                    'iteration' => $iteration,
                    'name' => $call->name,
                    'arguments' => $call->arguments,
                    'ok' => $invoked['result']->ok,
                    'count' => count($invoked['result']->items),
                    'truncated' => $invoked['result']->truncated,
                    'token_estimate' => $invoked['token_estimate'],
                ];
                $messages[] = [
                    'role' => 'tool',
                    'content' => $invoked['json'],
                ];

                if ($loopTokens >= $maxLoopTokens) {
                    break;
                }
            }
        }
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
