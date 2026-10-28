<?php

namespace App\Character\Exceptions;

use App\Exceptions\ConflictException;

class CharacterNotInChronicleException extends ConflictException
{
    public function __construct()
    {
        parent::__construct('Character does not belong to this chronicle.');
    }
}
