<?php

namespace App\Diary\Data;

final readonly class WriteEntryResult
{
    public function __construct(
        public bool $created,
        public int $processedMessages,
    ) {}
}
