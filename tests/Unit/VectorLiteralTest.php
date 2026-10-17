<?php

namespace Tests\Unit;

use App\Rag\VectorLiteral;
use PHPUnit\Framework\TestCase;

class VectorLiteralTest extends TestCase
{
    public function test_from_array_formats_pgvector_literal(): void
    {
        $literal = VectorLiteral::fromArray([0.1, 0.2, 1.0]);

        $this->assertSame('[0.1,0.2,1]', $literal);
    }
}
