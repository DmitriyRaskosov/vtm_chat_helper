<?php

namespace App\Chronicle\Exceptions;

use App\Exceptions\NotFoundException;

class InvitationNotFoundException extends NotFoundException
{
    public function __construct()
    {
        parent::__construct('Invitation not found.');
    }
}
