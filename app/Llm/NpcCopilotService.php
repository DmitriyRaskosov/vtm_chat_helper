<?php

namespace App\Llm;

use App\Context\ContextBuilder;
use RuntimeException;

class NpcCopilotService
{
    public function __construct(
        private ChatProvider $chat,
        private ContextBuilder $contextBuilder,
    ) {}

    public function drafts(string $npcName, string $prompt, int $sceneId): CopilotDraftResult
    {
        $draftCount = (int) config('copilot.draft_count');
        $context = $this->contextBuilder->build($npcName, $prompt, $sceneId, $draftCount);

        try {
            $raw = $this->chat->chat($context->messages);
        } catch (\Throwable $e) {
            throw new RuntimeException('Ollama is unavailable.', 0, $e);
        }

        return new CopilotDraftResult(
            $this->parseDrafts($raw, $draftCount),
            $context->metadata,
            (string) config('ollama.chat_model'),
            ContextBuilder::VERSION,
            ContextBuilder::PROMPT_VERSION,
        );
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

        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $raw, $matches)) {
            return $matches[1];
        }

        if (preg_match('/(\{[^{}]*"drafts"\s*:\s*\[.*?\]\s*\})/s', $raw, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
