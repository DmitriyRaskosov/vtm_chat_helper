<?php

namespace App\Extractor;

use App\Context\TokenEstimator;
use InvalidArgumentException;

class ExtractorTokenBudget
{
    private const OUTPUT_TOKEN_RESERVE = 256;

    private const MIN_OUTPUT_TOKENS = 512;

    public function __construct(private TokenEstimator $tokenEstimator) {}

    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    public function effectiveNumPredict(array $messages): int
    {
        $promptText = '';
        foreach ($messages as $message) {
            $promptText .= $message['content'];
        }

        $promptTokens = $this->tokenEstimator->estimate($promptText);
        $contextLength = (int) config('ollama.context_length');
        $configuredMax = (int) config('extractor.max_output_tokens');

        return min(
            $configuredMax,
            $contextLength - $promptTokens - self::OUTPUT_TOKEN_RESERVE,
        );
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    public function fits(array $messages): bool
    {
        return $this->effectiveNumPredict($messages) >= self::MIN_OUTPUT_TOKENS;
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    public function assertFits(array $messages): void
    {
        if (! $this->fits($messages)) {
            throw new InvalidArgumentException(
                'Source slice is too large for the extractor context window. Shorten the text or catalog.',
            );
        }
    }
}
