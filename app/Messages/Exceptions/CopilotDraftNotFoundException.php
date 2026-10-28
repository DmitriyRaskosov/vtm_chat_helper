<?php

namespace App\Messages\Exceptions;

use App\Exceptions\InvalidInputException;

class CopilotDraftNotFoundException extends InvalidInputException
{
    public function __construct()
    {
        parent::__construct('Selected Copilot draft does not exist.');
    }
}
