<?php

namespace App\Scene\Exceptions;

use App\Exceptions\DomainException;

class SceneDiceRollForbiddenException extends DomainException
{
    public function __construct(string $message = 'Нельзя бросать кубики за этого персонажа.')
    {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return 403;
    }
}
