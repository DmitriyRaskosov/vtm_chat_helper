<?php

namespace App\Scene\Exceptions;

use App\Exceptions\DomainException;

class InvalidLocationNodeInputException extends DomainException
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return 422;
    }
}
