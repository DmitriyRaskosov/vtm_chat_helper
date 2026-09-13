<?php

namespace App\Rulebook;

use RuntimeException;

class CannotDeleteRuleDocumentException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Rule documents cannot be deleted; archive them instead.');
    }
}
