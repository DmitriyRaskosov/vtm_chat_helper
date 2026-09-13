<?php

namespace App\Scene;

use RuntimeException;

class SceneFrozenException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('A closed scene cannot be changed.');
    }
}
