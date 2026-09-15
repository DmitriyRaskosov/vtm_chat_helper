<?php

namespace App\Extractor;

class ExtractionResponseParser
{
    /**
     * @return array{
     *     mentions: list<array{name: string, kind: string}>,
     *     relations: list<array{source: string, target: string, key: string}>,
     *     events: list<array<string, mixed>>,
     *     memories: list<array<string, mixed>>
     * }
     */
    public function parse(string $raw): array
    {
        $json = $this->extractJson($raw);
        if ($json === null) {
            throw new ExtractionParseException(jsonError: 'no json object');
        }

        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            throw new ExtractionParseException(jsonError: json_last_error_msg());
        }

        return [
            'mentions' => $this->parseMentions($decoded),
            'relations' => $this->parseRelations($decoded),
            'events' => $this->parseLooseList($decoded, 'events'),
            'memories' => $this->parseLooseList($decoded, 'memories'),
        ];
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return list<array{name: string, kind: string}>
     */
    private function parseMentions(array $decoded): array
    {
        $items = $decoded['mentions'] ?? [];
        if (! is_array($items)) {
            return [];
        }

        $mentions = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $name = trim((string) ($item['name'] ?? ''));
            $kind = trim((string) ($item['kind'] ?? ''));
            if ($name === '' || $kind === '') {
                continue;
            }

            $mentions[] = ['name' => $name, 'kind' => $kind];
        }

        return $mentions;
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return list<array{source: string, target: string, key: string}>
     */
    private function parseRelations(array $decoded): array
    {
        $items = $decoded['relations'] ?? [];
        if (! is_array($items)) {
            return [];
        }

        $relations = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $source = trim((string) ($item['source'] ?? ''));
            $target = trim((string) ($item['target'] ?? ''));
            $key = trim((string) ($item['key'] ?? ''));
            if ($source === '' || $target === '' || $key === '') {
                continue;
            }

            $relations[] = [
                'source' => $source,
                'target' => $target,
                'key' => $key,
            ];
        }

        return $relations;
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return list<array<string, mixed>>
     */
    private function parseLooseList(array $decoded, string $key): array
    {
        $items = $decoded[$key] ?? [];
        if (! is_array($items)) {
            return [];
        }

        $result = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                $result[] = $item;
            }
        }

        return $result;
    }

    private function extractJson(string $raw): ?string
    {
        $raw = $this->sanitizeJsonForDecode($this->stripThinking($raw));
        $trimmed = trim($raw);

        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $trimmed, $matches)) {
            $object = $this->extractBalancedJsonObject($matches[1]);
            if ($object !== null) {
                return $object;
            }
        }

        return $this->extractBalancedJsonObject($trimmed);
    }

    private function sanitizeJsonForDecode(string $json): string
    {
        $json = $this->normalizeNewlinesForJson($json);
        $repaired = preg_replace(
            '/(\{|,)\s*"(?:\s*")+\s*([A-Za-z_][A-Za-z0-9_]*)":/u',
            '$1 "$2":',
            $json,
        );

        return is_string($repaired) ? $repaired : $json;
    }

    private function normalizeNewlinesForJson(string $json): string
    {
        return str_replace(["\r\n", "\n", "\r"], ' ', $json);
    }

    private function stripThinking(string $raw): string
    {
        $stripped = preg_replace('/<think(?:ing)?>.*?<\/think(?:ing)?>/is', '', $raw);
        $stripped = is_string($stripped) ? $stripped : $raw;

        $stripped = preg_replace('/\*\*Thinking\.\.\.\*\*.*?\*\*\.\.\.done thinking\.\*\*/is', '', $stripped);
        $stripped = is_string($stripped) ? $stripped : $raw;

        $stripped = preg_replace('/Thinking\.\.\..*?\.\.\.done thinking\./is', '', $stripped);

        return trim(is_string($stripped) ? $stripped : $raw);
    }

    private function extractBalancedJsonObject(string $text): ?string
    {
        $start = strpos($text, '{');
        if ($start === false) {
            return null;
        }

        $depth = 0;
        $inString = false;
        $escape = false;
        $length = strlen($text);

        for ($i = $start; $i < $length; $i++) {
            $char = $text[$i];

            if ($inString) {
                if ($escape) {
                    $escape = false;

                    continue;
                }

                if ($char === '\\') {
                    $escape = true;

                    continue;
                }

                if ($char === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($char === '"') {
                $inString = true;

                continue;
            }

            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($text, $start, $i - $start + 1);
                }
            }
        }

        return null;
    }
}
