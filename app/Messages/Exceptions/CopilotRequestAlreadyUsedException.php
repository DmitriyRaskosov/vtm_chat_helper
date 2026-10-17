<?php

namespace App\Messages\Exceptions;

use App\Exceptions\ConflictException;

class CopilotRequestAlreadyUsedException extends ConflictException
{
    public function __construct()
    {
        parent::__construct('Copilot request was already used.');
    }
}
