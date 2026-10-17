<?php

namespace App\Llm\Exceptions;

use App\Exceptions\UpstreamException;

class LlmUnavailableException extends UpstreamException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('LLM provider is unavailable.', 0, $previous);
    }

    public function statusCode(): int
    {
        return 503;
    }
}
