<?php

namespace App\Chronicle\Exceptions;

use App\Exceptions\ConflictException;

class InvitationUnavailableException extends ConflictException
{
    public function __construct(
        string $message,
        private readonly ?int $chronicleId = null,
    ) {
        parent::__construct($message);
    }

    public function chronicleId(): ?int
    {
        return $this->chronicleId;
    }
}
