<?php

namespace App\Lore;

use RuntimeException;

class CannotDeleteLoreEntryException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Lore entries cannot be deleted; archive them instead.');
    }
}
