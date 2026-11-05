<?php

namespace App\Scene\Exceptions;

use App\Exceptions\UpstreamException;

class LocationNodeParseFailedException extends UpstreamException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('Не удалось разобрать локацию на объекты.', 0, $previous);
    }

    public function statusCode(): int
    {
        return 503;
    }
}
