<?php

namespace App\Character;

use RuntimeException;

class CharacterAffiliationRevisionException extends RuntimeException
{
    public function __construct(int $expected, int $actual)
    {
        parent::__construct(
            "Character affiliation revision {$actual} does not match expected {$expected}.",
        );
    }
}
