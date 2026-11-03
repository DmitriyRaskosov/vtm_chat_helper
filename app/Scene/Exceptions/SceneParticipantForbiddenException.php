<?php

namespace App\Scene\Exceptions;

use App\Exceptions\DomainException;

class SceneParticipantForbiddenException extends DomainException
{
    public function __construct(string $message = 'Нельзя управлять этим участником сцены.')
    {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return 403;
    }
}
