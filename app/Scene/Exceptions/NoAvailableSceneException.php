<?php

namespace App\Scene\Exceptions;

use App\Exceptions\ConflictException;

class NoAvailableSceneException extends ConflictException
{
    public function __construct()
    {
        parent::__construct('There is no available scene in the active game session.');
    }
}
