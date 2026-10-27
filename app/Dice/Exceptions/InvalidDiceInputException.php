<?php

namespace App\Dice\Exceptions;

use App\Exceptions\InvalidInputException;

class InvalidDiceInputException extends InvalidInputException
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}
