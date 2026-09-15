<?php

namespace App\Extractor;

use RuntimeException;

class ExtractionParseException extends RuntimeException
{
    public function __construct(
        string $message = 'Could not parse graph extraction response from the model.',
        public readonly string $jsonError = 'No error',
    ) {
        parent::__construct($message);
    }
}
