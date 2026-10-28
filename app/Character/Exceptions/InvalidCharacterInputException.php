<?php

namespace App\Character\Exceptions;

use App\Exceptions\InvalidInputException;

class InvalidCharacterInputException extends InvalidInputException
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}
