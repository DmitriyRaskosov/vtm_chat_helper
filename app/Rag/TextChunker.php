<?php

namespace App\Rag;

use App\Context\TokenEstimator;

class TextChunker
{
    public const DEFAULT_MAX_TOKENS = 400;

    public function __construct(private TokenEstimator $tokens) {}

    /**
     * @return list<string>
     */
    public function split(string $content, int $maxTokens = self::DEFAULT_MAX_TOKENS): array
    {
        $content = trim($content);

        if ($content === '') {
            return [];
        }

        if ($this->tokens->estimate($content) <= $maxTokens) {
            return [$content];
        }

        $paragraphs = preg_split('/\n{2,}/', $content) ?: [$content];
        $chunks = [];
        $buffer = '';

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);

            if ($paragraph === '') {
                continue;
            }

            $candidate = $buffer === '' ? $paragraph : $buffer."\n\n".$paragraph;

            if ($buffer !== '' && $this->tokens->estimate($candidate) > $maxTokens) {
                $chunks[] = $buffer;
                $buffer = $paragraph;
            } else {
                $buffer = $candidate;
            }
        }

        if ($buffer !== '') {
            $chunks[] = $buffer;
        }

        return $chunks === [] ? [$content] : $chunks;
    }
}
