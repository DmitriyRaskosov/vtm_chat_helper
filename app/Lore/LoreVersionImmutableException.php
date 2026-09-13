<?php

namespace App\Lore;

use RuntimeException;

class LoreVersionImmutableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Lore entry versions are immutable snapshots.');
    }
}
