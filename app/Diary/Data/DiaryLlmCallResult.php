<?php

namespace App\Diary\Data;

final readonly class DiaryLlmCallResult
{
    public function __construct(
        public string $content,
        public bool $isTruncated,
    ) {}
}
