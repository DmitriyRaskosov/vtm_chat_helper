<?php

namespace App\Llm\Exceptions;

use RuntimeException;

class LlmUnavailableException extends RuntimeException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('LLM provider is unavailable.', 0, $previous);
    }
}
