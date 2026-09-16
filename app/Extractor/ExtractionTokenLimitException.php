<?php

namespace App\Extractor;

use RuntimeException;

class ExtractionTokenLimitException extends RuntimeException
{
    public function __construct(string $profile = 'lore')
    {
        $envKey = match ($profile) {
            'scene' => 'EXTRACTOR_SCENE_OUTPUT_TOKENS',
            'biography' => 'EXTRACTOR_BIOGRAPHY_OUTPUT_TOKENS',
            default => 'EXTRACTOR_LORE_OUTPUT_TOKENS',
        };

        $limit = (int) config("extractor.profiles.{$profile}.output_tokens");

        parent::__construct(
            "Extractor output token limit reached ({$envKey}={$limit}). "
            ."Increase {$envKey} or shorten the source slice.",
        );
    }
}
