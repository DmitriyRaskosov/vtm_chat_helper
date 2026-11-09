<?php

namespace App\Combat\Exceptions;

use App\Exceptions\InvalidInputException;

class CharacterNotInSceneException extends InvalidInputException
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}
