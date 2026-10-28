<?php

namespace App\World\Exceptions;

use App\Exceptions\ConflictException;

class PlayerNotChronicleMemberException extends ConflictException
{
    public function __construct()
    {
        parent::__construct('The user is not an active member of this chronicle.');
    }
}
