<?php

namespace App\Messages\Exceptions;

use App\Exceptions\ConflictException;

class CopilotRequestMismatchException extends ConflictException
{
    public function __construct()
    {
        parent::__construct('Copilot request does not match this message.');
    }
}
