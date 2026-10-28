<?php

namespace App\Scene\Exceptions;

use App\Exceptions\ConflictException;

class InactiveGameSessionForSceneException extends ConflictException
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}
