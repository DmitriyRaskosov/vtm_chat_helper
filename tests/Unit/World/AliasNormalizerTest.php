<?php

namespace Tests\Unit\World;

use App\World\AliasNormalizer;
use Tests\TestCase;

class AliasNormalizerTest extends TestCase
{
    public function test_it_lowercases_and_collapses_whitespace(): void
    {
        $this->assertSame('зал принца', AliasNormalizer::normalize('  Зал   Принца  '));
        $this->assertSame('элизиум', AliasNormalizer::slug('Элизиум'));
        $this->assertSame('zal-printsa', AliasNormalizer::slug('Zal Printsa!'));
        $this->assertSame('entity', AliasNormalizer::slug('!!!'));
    }
}
