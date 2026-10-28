<?php

namespace App\Llm\Exceptions;

use App\Exceptions\UpstreamException;

class LlmResponseInvalidException extends UpstreamException
{
    public function __construct()
    {
        parent::__construct('Could not parse draft replies from the model response.');
    }

    public function statusCode(): int
    {
        return 502;
    }
}
