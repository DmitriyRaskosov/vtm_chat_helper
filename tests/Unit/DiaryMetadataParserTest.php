<?php

namespace Tests\Unit;

use App\Diary\DiaryMetadataParser;
use App\Enums\DiaryEmotion;
use PHPUnit\Framework\TestCase;

class DiaryMetadataParserTest extends TestCase
{
    public function test_metadata_parser_extracts_prefix(): void
    {
        $parsed = DiaryMetadataParser::parse("[0.850 anger]\n\nНочь первая. Пустырь.");

        $this->assertSame(0.85, $parsed['importance']);
        $this->assertSame(DiaryEmotion::Anger, $parsed['emotion']);
        $this->assertSame('Ночь первая. Пустырь.', $parsed['entry']);
    }

    public function test_metadata_parser_falls_back_when_no_prefix(): void
    {
        $parsed = DiaryMetadataParser::parse('Ночь первая. Пустырь.');

        $this->assertNull($parsed['importance']);
        $this->assertNull($parsed['emotion']);
        $this->assertSame('Ночь первая. Пустырь.', $parsed['entry']);
    }

    public function test_metadata_parser_normalizes_aliases(): void
    {
        $parsed = DiaryMetadataParser::parse("[0.700 fury]\n\nтекст");

        $this->assertSame(DiaryEmotion::Anger, $parsed['emotion']);
    }

    public function test_metadata_parser_clamps_out_of_range_importance(): void
    {
        $parsed = DiaryMetadataParser::parse("[1.500 joy]\n\nтекст");

        $this->assertSame(1.0, $parsed['importance']);
    }
}
