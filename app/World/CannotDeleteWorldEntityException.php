<?php

namespace App\World;

use RuntimeException;

class CannotDeleteWorldEntityException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('World entities cannot be deleted; archive them instead.');
    }
}
