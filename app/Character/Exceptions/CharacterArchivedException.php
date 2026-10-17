<?php

namespace App\Character\Exceptions;

use App\Exceptions\ConflictException;

class CharacterArchivedException extends ConflictException
{
    public function __construct()
    {
        parent::__construct('Character is archived.');
    }
}
