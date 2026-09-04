<?php

namespace App\Context;

use App\Llm\ChatProvider;
use RuntimeException;

class StorytellerIntentGenerator
{
    public const PROMPT_VERSION = 'storyteller-intent-v1';

    public function __construct(private ChatProvider $chat) {}

    /**
     * @param  list<string>  $sourceTexts
     */
    public function generate(?string $previousIntent, array $sourceTexts): string
    {
        $sources = implode("\n\n---\n\n", $sourceTexts);
        $previous = $previousIntent !== null && $previousIntent !== ''
            ? "Previous intention memory:\n{$previousIntent}\n\n"
            : '';

        $raw = $this->chat->chat([
            [
                'role' => 'system',
                'content' => <<<'PROMPT'
You compress a storyteller's Copilot intentions for a Vampire: The Masquerade chat.
These are meta instructions about tone, pacing, secrets, and dramatic direction.
They are not in-world events and must not be written as chronicle facts.
Respond with valid JSON only:
{"narrative":"short rolling summary of ongoing storyteller intentions"}
PROMPT,
            ],
            [
                'role' => 'user',
                'content' => $previous."New Copilot prompts:\n{$sources}",
            ],
        ], [
            'num_ctx' => (int) config('context.intent.context_length', 8192),
            'num_predict' => (int) config('context.intent.max_output_tokens', 400),
        ]);

        $decoded = json_decode($this->extractJson($raw), true);
        if (! is_array($decoded) || ! isset($decoded['narrative']) || ! is_string($decoded['narrative'])) {
            throw new RuntimeException('Could not parse storyteller intent summary.');
        }

        $content = trim($decoded['narrative']);
        if ($content === '') {
            throw new RuntimeException('Storyteller intent summary is empty.');
        }

        return $content;
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

        throw new RuntimeException('Could not parse storyteller intent summary.');
    }
}
