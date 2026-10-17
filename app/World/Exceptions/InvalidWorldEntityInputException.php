<?php

namespace App\World\Exceptions;

use App\Exceptions\InvalidInputException;

class InvalidWorldEntityInputException extends InvalidInputException
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}
