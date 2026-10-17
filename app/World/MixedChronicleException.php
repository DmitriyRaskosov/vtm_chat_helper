<?php

namespace App\World;

use App\Exceptions\ConflictException;

class MixedChronicleException extends ConflictException
{
    public function __construct()
    {
        parent::__construct('World entities must belong to the same chronicle.');
    }
}
