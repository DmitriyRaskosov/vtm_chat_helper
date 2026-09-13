<?php

namespace App\Character;

use RuntimeException;

class CharacterStatusRevisionException extends RuntimeException
{
    public function __construct(int $expected, int $actual)
    {
        parent::__construct(
            "Character status revision {$actual} does not match expected {$expected}.",
        );
    }
}
