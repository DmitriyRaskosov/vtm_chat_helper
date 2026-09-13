<?php

namespace App\Scene;

use RuntimeException;

class SceneContextRevisionException extends RuntimeException
{
    public function __construct(int $expected, int $actual)
    {
        parent::__construct(
            "Scene context revision {$actual} does not match expected {$expected}.",
        );
    }
}
