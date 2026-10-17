<?php

namespace App\Scene\Exceptions;

use App\Exceptions\InvalidInputException;

class InvalidSceneContextInputException extends InvalidInputException
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}
