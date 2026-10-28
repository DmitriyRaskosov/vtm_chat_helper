<?php

namespace App\Diary\Exceptions;

use App\Exceptions\InvalidInputException;

class InvalidDiaryEntryInputException extends InvalidInputException
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}
