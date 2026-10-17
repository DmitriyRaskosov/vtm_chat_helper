<?php

namespace App\Messages\Exceptions;

use App\Exceptions\DomainException;

/**
 * Авторизация CopilotRequest — перенесётся в Policy (задача 030a).
 */
class CopilotRequestForbiddenException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Copilot request belongs to another storyteller.');
    }

    public function statusCode(): int
    {
        return 403;
    }
}
