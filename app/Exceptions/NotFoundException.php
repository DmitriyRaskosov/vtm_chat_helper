<?php

namespace App\Exceptions;

abstract class NotFoundException extends DomainException
{
    public function statusCode(): int
    {
        return 404;
    }
}
