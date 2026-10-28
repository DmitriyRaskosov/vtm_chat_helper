<?php

namespace App\Context;

use InvalidArgumentException;

class TokenEstimator
{
    public const ALGORITHM = 'unicode-chars-v1';

    public function estimate(string $text): int
    {
        $charactersPerToken = $this->charactersPerToken();

        return max(1, (int) ceil(mb_strlen($text) / $charactersPerToken));
    }

    public function version(): string
    {
        return self::ALGORITHM.'-cpt'.$this->charactersPerToken();
    }

    private function charactersPerToken(): int
    {
        $charactersPerToken = (int) config('context.token_estimator.characters_per_token', 3);
        if ($charactersPerToken < 1) {
            throw new InvalidArgumentException('Characters per token must be at least 1.');
        }

        return $charactersPerToken;
    }
}
