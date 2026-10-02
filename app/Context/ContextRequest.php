<?php

namespace App\Context;

final readonly class ContextRequest
{
    /**
     * @param  list<string>  $searchTopics
     */
    public function __construct(
        public string $npcName,
        public string $prompt,
        public int $sceneId,
        public int $draftCount,
        public ?int $storytellerId = null,
        public ?int $gameSessionId = null,
        public ?int $characterId = null,
        public ContextPass $pass = ContextPass::Reply,
        public array $searchTopics = [],
        public ?int $historyLimit = null,
        public ?int $maxInputTokens = null,
        public ?int $maxOutputTokens = null,
        public bool $compactIdentity = false,
    ) {}

    public function historyLimit(): int
    {
        if ($this->historyLimit !== null) {
            return $this->historyLimit;
        }

        if ($this->pass === ContextPass::Topics) {
            return (int) config('copilot.topic_history_limit', 8);
        }

        return (int) config('copilot.history_limit');
    }

    public function maxInputTokens(): int
    {
        if ($this->maxInputTokens !== null) {
            return $this->maxInputTokens;
        }

        if ($this->pass === ContextPass::Topics) {
            return (int) config('context.topic.max_input_tokens', 8000);
        }

        return (int) config('context.copilot.max_input_tokens', 12000);
    }

    public function maxOutputTokens(): int
    {
        if ($this->maxOutputTokens !== null) {
            return $this->maxOutputTokens;
        }

        if ($this->pass === ContextPass::Topics) {
            return (int) config('copilot.topic_max_output_tokens', 384);
        }

        return (int) config('ollama.max_output_tokens', 3000);
    }

    /**
     * Query for vector + graph retrieval. Topics when present, else the ST prompt.
     */
    public function retrievalQuery(): string
    {
        $parts = [];

        $prompt = trim($this->prompt);
        if ($prompt !== '') {
            $parts[] = $prompt;
        }

        $topics = [];
        foreach ($this->searchTopics as $topic) {
            if (is_string($topic) && trim($topic) !== '') {
                $topics[] = trim($topic);
            }
        }
        $topics = array_slice(array_values(array_unique($topics)), 0, 2);

        if ($topics !== []) {
            $parts[] = implode('. ', $topics);
        }

        return implode("\n", $parts);
    }
}
