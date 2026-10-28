<?php

namespace App\Character;

use RuntimeException;

class BiographyVersionImmutableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Character biography versions are immutable snapshots.');
    }
}
