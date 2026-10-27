<?php

namespace App\Exceptions;

abstract class ConflictException extends DomainException
{
    public function statusCode(): int
    {
        return 409;
    }
}
