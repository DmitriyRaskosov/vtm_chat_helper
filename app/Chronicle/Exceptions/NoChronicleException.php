<?php

namespace App\Chronicle\Exceptions;

use App\Exceptions\ConflictException;

class NoChronicleException extends ConflictException
{
    public function __construct()
    {
        parent::__construct('There is no available chronicle.');
    }
}
