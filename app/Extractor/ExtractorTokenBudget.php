<?php

namespace App\Extractor;

use InvalidArgumentException;

class ExtractorTokenBudget
{
    public function estimateTokens(string $text): int
    {
        $charactersPerToken = max(1, (int) config('extractor.characters_per_token', 2));

        return max(1, (int) ceil(mb_strlen($text) / $charactersPerToken));
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    public function promptTokens(array $messages): int
    {
        $total = 0;
        foreach ($messages as $message) {
            $total += $this->estimateTokens($message['content']);
        }

        return $total;
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    public function effectiveNumPredict(array $messages, string $profile): int
    {
        $promptTokens = $this->promptTokens($messages);
        $contextLength = (int) config('ollama.context_length');
        $configuredMax = (int) config("extractor.profiles.{$profile}.output_tokens");
        $reserve = (int) config('extractor.context_reserve_tokens', 256);

        return min(
            $configuredMax,
            $contextLength - $promptTokens - $reserve,
        );
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    public function fits(array $messages, string $profile): bool
    {
        return $this->effectiveNumPredict($messages, $profile) >= (int) config('extractor.min_output_tokens', 512);
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    public function assertFits(array $messages, string $profile): void
    {
        if (! $this->fits($messages, $profile)) {
            throw new InvalidArgumentException(
                'Source slice is too large for the extractor context window. Shorten the text or catalog.',
            );
        }
    }

    public function profileCatalogTokenLimit(string $profile): int
    {
        return (int) config("extractor.profiles.{$profile}.catalog_tokens", 1000);
    }

    public function profileSceneInputTokenLimit(): int
    {
        return (int) config('extractor.profiles.scene.input_tokens', 8192);
    }

    public function profileSceneFeedTokenLimit(): int
    {
        return (int) config('extractor.profiles.scene.feed_tokens', 5392);
    }
}
