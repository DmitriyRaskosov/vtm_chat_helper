<?php

namespace App\World;

use InvalidArgumentException;

class MixedChronicleException extends InvalidArgumentException
{
    public function __construct()
    {
        parent::__construct('World entities must belong to the same chronicle.');
    }
}
