<?php

namespace App\Scene\Exceptions;

use App\Exceptions\ConflictException;

class InactiveSceneForMessagesException extends ConflictException
{
    public function __construct()
    {
        parent::__construct('Messages can only be posted to the active scene.');
    }
}
