<?php

namespace App\Scene\Exceptions;

use App\Exceptions\UpstreamException;

class EventGenerationFailedException extends UpstreamException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('Не удалось сгенерировать описание.', 0, $previous);
    }

    public function statusCode(): int
    {
        return 503;
    }
}
