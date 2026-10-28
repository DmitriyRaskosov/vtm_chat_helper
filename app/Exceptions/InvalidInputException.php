<?php

namespace App\Exceptions;

abstract class InvalidInputException extends DomainException
{
    public function statusCode(): int
    {
        return 422;
    }
}
