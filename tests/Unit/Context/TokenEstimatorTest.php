<?php

namespace Tests\Unit\Context;

use App\Context\TokenEstimator;
use InvalidArgumentException;
use Tests\TestCase;

class TokenEstimatorTest extends TestCase
{
    public function test_it_uses_a_conservative_unicode_character_estimate(): void
    {
        config()->set('context.token_estimator.characters_per_token', 3);

        $estimator = new TokenEstimator;

        $this->assertSame(1, $estimator->estimate(''));
        $this->assertSame(1, $estimator->estimate('кот'));
        $this->assertSame(2, $estimator->estimate('Каинит'));
        $this->assertSame('unicode-chars-v1-cpt3', $estimator->version());
    }

    public function test_it_rejects_an_invalid_ratio(): void
    {
        config()->set('context.token_estimator.characters_per_token', 0);

        $this->expectException(InvalidArgumentException::class);

        (new TokenEstimator)->estimate('text');
    }
}
