<?php

namespace App\Context;

use App\Enums\ContextSummaryLevel;
use App\Llm\ChatProvider;
use RuntimeException;

class SummaryGenerator
{
    public const PROMPT_VERSION = 'context-summary-v1';

    public function __construct(private ChatProvider $chat) {}

    /**
     * @param  list<string>  $sourceTexts
     */
    public function generate(ContextSummaryLevel $level, string $scopeTitle, array $sourceTexts): GeneratedSummary
    {
        $sources = implode("\n\n---\n\n", $sourceTexts);
        $raw = $this->chat->chat([
            [
                'role' => 'system',
                'content' => <<<'PROMPT'
You summarize Vampire: The Masquerade game history. Preserve concrete facts, character actions, decisions, consequences, locations, and unresolved threads. Do not invent facts.
Respond with valid JSON only:
{"narrative":"concise chronological summary","participants":[],"locations":[],"key_events":[],"facts":[],"decisions":[],"unresolved_threads":[]}
PROMPT,
            ],
            [
                'role' => 'user',
                'content' => "Summary level: {$level->value}\nScope: {$scopeTitle}\n\nSources:\n{$sources}",
            ],
        ], [
            'num_ctx' => (int) config('context.summaries.context_length', 24576),
            'num_predict' => (int) config('context.summaries.max_output_tokens', 3000),
        ]);

        $decoded = json_decode($this->extractJson($raw), true);
        if (! is_array($decoded) || ! isset($decoded['narrative']) || ! is_string($decoded['narrative'])) {
            throw new RuntimeException('Could not parse context summary from the model response.');
        }

        $content = trim($decoded['narrative']);
        if ($content === '') {
            throw new RuntimeException('Context summary narrative is empty.');
        }

        $metadata = [];
        foreach (['participants', 'locations', 'key_events', 'facts', 'decisions', 'unresolved_threads'] as $key) {
            $items = $decoded[$key] ?? [];
            $metadata[$key] = is_array($items)
                ? array_values(array_filter($items, is_string(...)))
                : [];
        }

        return new GeneratedSummary($content, $metadata);
    }

    private function extractJson(string $raw): string
    {
        $trimmed = trim($raw);
        if (str_starts_with($trimmed, '{')) {
            return $trimmed;
        }

        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $raw, $matches)) {
            return $matches[1];
        }

        throw new RuntimeException('Could not parse context summary from the model response.');
    }
}
