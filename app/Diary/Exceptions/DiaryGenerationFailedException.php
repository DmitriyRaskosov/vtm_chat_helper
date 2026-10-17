<?php

namespace App\Diary\Exceptions;

use RuntimeException;

class DiaryGenerationFailedException extends RuntimeException
{
    public function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
