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

    public function drafts(string $npcName, string $prompt, int $sceneId, int $storytellerId): CopilotDraftResult
    {
        $draftCount = (int) config('copilot.draft_count');
        $scene = Scene::query()->findOrFail($sceneId);
        $context = $this->contextBuilder->build(
            $npcName,
            $prompt,
            $sceneId,
            $draftCount,
            $storytellerId,
            (int) $scene->game_session_id,
        );

        $messages = $context->messages;
        $metadata = $context->metadata;
        $toolInvocations = [];
        $loopTokens = 0;

        try {
            $raw = $this->completeWithTools($messages, $scene, $toolInvocations, $loopTokens);
        } catch (\Throwable $e) {
            throw new RuntimeException('Ollama is unavailable.', 0, $e);
        }

        $metadata['tool_iterations'] = count(array_unique(array_column($toolInvocations, 'iteration')));
        $metadata['tool_invocations'] = $toolInvocations;
        $metadata['tool_loop_token_estimate'] = $loopTokens;

        return new CopilotDraftResult(
            $this->parseDrafts($raw, $draftCount),
            $metadata,
            (string) config('ollama.chat_model'),
            ContextBuilder::VERSION,
            ContextBuilder::PROMPT_VERSION,
        );
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

        while (true) {
            $allowTools = $enabled && $iteration < $maxIterations && $loopTokens < $maxLoopTokens;
            $turn = $this->chat->chatTurn($messages, [], $allowTools ? $definitions : []);

            if ($turn->toolCalls === []) {
                return $turn->content;
            }

            if (! $allowTools) {
                $messages[] = $turn->toAssistantMessage();
                $messages[] = [
                    'role' => 'user',
                    'content' => 'Stop using tools. Respond with JSON drafts only.',
                ];
                $final = $this->chat->chatTurn($messages, [], []);

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
