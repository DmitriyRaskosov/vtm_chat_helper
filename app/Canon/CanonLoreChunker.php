<?php

namespace App\Canon;

use App\Context\TokenEstimator;

final class CanonLoreChunker
{
    public const MAX_CHUNK_TOKENS = 340;

    public function __construct(
        private TokenEstimator $tokenEstimator,
    ) {}

    /**
     * @return list<array{header: string, content: string, token_estimate: int}>
     */
    public function chunkMarkdownBody(string $body): array
    {
        $body = trim($body);
        if ($body === '') {
            return [];
        }

        $sections = $this->parseSections($body);
        $chunks = [];

        foreach ($sections as $section) {
            foreach ($this->splitSection($section['header'], $section['content']) as $piece) {
                $chunks[] = $piece;
            }
        }

        return $chunks;
    }

    /**
     * @return list<array{header: string, content: string}>
     */
    private function parseSections(string $body): array
    {
        $parts = preg_split('/(?=^##\s+)/m', $body, -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === false || $parts === []) {
            return [['header' => '', 'content' => $body]];
        }

        $sections = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            if (preg_match('/^##\s+(.+?)(?:\r?\n(.*))?$/s', $part, $matches)) {
                $sections[] = [
                    'header' => trim($matches[1]),
                    'content' => trim($matches[2] ?? ''),
                ];

                continue;
            }

            $sections[] = ['header' => '', 'content' => $part];
        }

        return $sections === [] ? [['header' => '', 'content' => $body]] : $sections;
    }

    /**
     * @return list<array{header: string, content: string, token_estimate: int}>
     */
    private function splitSection(string $header, string $content): array
    {
        $content = trim($content);
        if ($content === '') {
            return [];
        }

        if ($this->tokenEstimator->estimate($content) <= self::MAX_CHUNK_TOKENS) {
            return [$this->makeChunk($header, $content)];
        }

        $paragraphs = preg_split('/\n\s*\n/u', $content) ?: [];
        $paragraphs = array_values(array_filter(array_map('trim', $paragraphs), static fn (string $p): bool => $p !== ''));

        if ($paragraphs === []) {
            return $this->splitOversizedText($header, $content);
        }

        $chunks = [];
        $buffer = '';

        foreach ($paragraphs as $paragraph) {
            if ($this->tokenEstimator->estimate($paragraph) > self::MAX_CHUNK_TOKENS) {
                if ($buffer !== '') {
                    $chunks[] = $this->makeChunk($header, $buffer);
                    $buffer = '';
                }
                array_push($chunks, ...$this->splitOversizedText($header, $paragraph));

                continue;
            }

            $candidate = $buffer === '' ? $paragraph : $buffer."\n\n".$paragraph;
            if ($this->tokenEstimator->estimate($candidate) <= self::MAX_CHUNK_TOKENS) {
                $buffer = $candidate;

                continue;
            }

            $chunks[] = $this->makeChunk($header, $buffer);
            $buffer = $paragraph;
        }

        if ($buffer !== '') {
            $chunks[] = $this->makeChunk($header, $buffer);
        }

        return $chunks;
    }

    /**
     * @return list<array{header: string, content: string, token_estimate: int}>
     */
    private function splitOversizedText(string $header, string $text): array
    {
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);
        if ($words === false || $words === []) {
            return [$this->makeChunk($header, $text)];
        }

        $chunks = [];
        $buffer = '';

        foreach ($words as $word) {
            $candidate = $buffer === '' ? $word : $buffer.' '.$word;
            if ($this->tokenEstimator->estimate($candidate) <= self::MAX_CHUNK_TOKENS) {
                $buffer = $candidate;

                continue;
            }

            if ($buffer !== '') {
                $chunks[] = $this->makeChunk($header, $buffer);
            }

            if ($this->tokenEstimator->estimate($word) > self::MAX_CHUNK_TOKENS) {
                $chunks[] = $this->makeChunk($header, $word);
                $buffer = '';

                continue;
            }

            $buffer = $word;
        }

        if ($buffer !== '') {
            $chunks[] = $this->makeChunk($header, $buffer);
        }

        return $chunks;
    }

    /**
     * @return array{header: string, content: string, token_estimate: int}
     */
    private function makeChunk(string $header, string $content): array
    {
        return [
            'header' => $header,
            'content' => $content,
            'token_estimate' => $this->tokenEstimator->estimate($content),
        ];
    }
}
