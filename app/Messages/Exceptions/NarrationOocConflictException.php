<?php

namespace App\Messages\Exceptions;

use App\Exceptions\InvalidInputException;

class NarrationOocConflictException extends InvalidInputException
{
    public function __construct()
    {
        parent::__construct('Narration cannot be marked out of character.');
    }
}
