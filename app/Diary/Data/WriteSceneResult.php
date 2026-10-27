<?php

namespace App\Diary\Data;

final readonly class WriteSceneResult
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(
        public int $created,
        public int $npcs,
        public int $processedMessages,
        public array $errors,
    ) {}
}
