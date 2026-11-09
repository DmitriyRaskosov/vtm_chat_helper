<?php

namespace App\Combat\Exceptions;

use App\Exceptions\ConflictException;

class DuplicateCombatParticipantException extends ConflictException
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}
