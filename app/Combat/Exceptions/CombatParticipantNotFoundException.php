<?php

namespace App\Combat\Exceptions;

use App\Exceptions\NotFoundException;

class CombatParticipantNotFoundException extends NotFoundException
{
    public function __construct()
    {
        parent::__construct('Combat participant not found.');
    }
}
