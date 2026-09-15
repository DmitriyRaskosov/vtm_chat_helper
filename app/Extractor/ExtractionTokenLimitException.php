<?php

namespace App\Extractor;

use RuntimeException;

class ExtractionTokenLimitException extends RuntimeException
{
    public function __construct()
    {
        $limit = (int) config('extractor.max_output_tokens');

        parent::__construct(
            "Extractor output token limit reached (EXTRACTOR_MAX_OUTPUT_TOKENS={$limit}). "
            .'Increase EXTRACTOR_MAX_OUTPUT_TOKENS or shorten the source slice.',
        );
    }
}
