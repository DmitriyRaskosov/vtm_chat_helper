<?php

namespace App\Scene\Exceptions;

use App\Exceptions\ConflictException;

class CopilotRequiresActiveSceneException extends ConflictException
{
    public function __construct()
    {
        parent::__construct('Copilot requires an active scene.');
    }
}
