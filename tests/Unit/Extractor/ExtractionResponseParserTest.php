<?php

namespace Tests\Unit\Extractor;

use App\Extractor\ExtractionParseException;
use App\Extractor\ExtractionResponseParser;
use Tests\TestCase;

class ExtractionResponseParserTest extends TestCase
{
    public function test_it_parses_json_after_qwen_thinking_block(): void
    {
        $raw = <<<'TEXT'
**Thinking...**

The user wants JSON only with empty arrays.

**...done thinking.**

{"mentions":[{"name":"Камарилья","kind":"faction"}],"relations":[],"events":[],"memories":[]}
TEXT;

        $parsed = (new ExtractionResponseParser)->parse($raw);

        $this->assertSame('Камарилья', $parsed['mentions'][0]['name']);
        $this->assertSame('faction', $parsed['mentions'][0]['kind']);
    }

    public function test_it_strips_think_tags_before_json(): void
    {
        $raw = "<think>reasoning</think>\n".'{"mentions":[],"relations":[],"events":[],"memories":[]}';

        $parsed = (new ExtractionResponseParser)->parse($raw);

        $this->assertSame([], $parsed['mentions']);
    }

    public function test_it_extracts_first_balanced_object_when_trailing_text_follows(): void
    {
        $raw = '{"mentions":[],"relations":[],"events":[],"memories":[]}

Extra commentary from the model.';

        $parsed = (new ExtractionResponseParser)->parse($raw);

        $this->assertSame([], $parsed['relations']);
    }

    public function test_it_parses_pretty_printed_json_with_newlines_between_tokens(): void
    {
        $raw = <<<'JSON'
{
  "mentions": [{"name": "Камарилья", "kind": "faction"}],
  "relations": [],
  "events": [],
  "memories": []
}
JSON;

        $parsed = (new ExtractionResponseParser)->parse($raw);

        $this->assertSame('Камарилья', $parsed['mentions'][0]['name']);
        $this->assertSame([], $parsed['memories']);
    }

    public function test_it_replaces_bare_newline_inside_string_with_space(): void
    {
        $raw = '{"mentions":[],"relations":[],"events":[],"memories":[{"character":"Виктория","text":"line one'."\n".'line two","node_type":"event"}]}';

        $parsed = (new ExtractionResponseParser)->parse($raw);

        $this->assertSame('line one line two', $parsed['memories'][0]['text']);
    }

    public function test_it_preserves_escaped_newline_in_string_values(): void
    {
        $raw = '{"mentions":[],"relations":[],"events":[],"memories":[{"character":"Виктория","text":"line one\\nline two","node_type":"event"}]}';

        $parsed = (new ExtractionResponseParser)->parse($raw);

        $this->assertSame("line one\nline two", $parsed['memories'][0]['text']);
    }

    public function test_it_rejects_plain_text_without_json(): void
    {
        try {
            (new ExtractionResponseParser)->parse('**Thinking...** still thinking');
            $this->fail('Expected ExtractionParseException');
        } catch (ExtractionParseException $e) {
            $this->assertSame('no json object', $e->jsonError);
        }
    }

    public function test_it_exposes_json_last_error_for_invalid_object(): void
    {
        try {
            (new ExtractionResponseParser)->parse('{not json}');
            $this->fail('Expected ExtractionParseException');
        } catch (ExtractionParseException $e) {
            $this->assertSame('Syntax error', $e->jsonError);
        }
    }

    public function test_it_repairs_stray_quote_before_object_key(): void
    {
        $raw = <<<'JSON'
{
  "mentions": [
    { "name": "Камарилья", "kind": "faction" },
    { " "name": "Гангрел", "kind": "clan" }
  ],
  "relations": [],
  "events": [],
  "memories": []
}
JSON;

        $parsed = (new ExtractionResponseParser)->parse($raw);

        $this->assertSame('Камарилья', $parsed['mentions'][0]['name']);
        $this->assertSame('Гангрел', $parsed['mentions'][1]['name']);
        $this->assertSame('clan', $parsed['mentions'][1]['kind']);
    }

    public function test_it_repairs_doubled_quote_before_object_key(): void
    {
        $raw = <<<'JSON'
{
  "mentions": [
    { "name": "Камарилья", "kind": "faction" },
    { ""name": "Потомство", "kind": "concept" }
  ],
  "relations": [],
  "events": [],
  "memories": []
}
JSON;

        $parsed = (new ExtractionResponseParser)->parse($raw);

        $this->assertSame('Камарилья', $parsed['mentions'][0]['name']);
        $this->assertSame('Потомство', $parsed['mentions'][1]['name']);
        $this->assertSame('concept', $parsed['mentions'][1]['kind']);
    }

    public function test_it_keeps_empty_string_values(): void
    {
        $raw = '{"mentions":[],"relations":[],"events":[],"memories":[{"character":"Виктория","text":"","node_type":"fact"}]}';

        $parsed = (new ExtractionResponseParser)->parse($raw);

        $this->assertSame('', $parsed['memories'][0]['text']);
    }
}
