<?php

namespace App\Rulebook;

use RuntimeException;

class RuleVersionImmutableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Rule document versions are immutable snapshots.');
    }
}
