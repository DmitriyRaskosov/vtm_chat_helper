<?php

namespace App\Diary\Data;

final readonly class SummarizeSceneResult
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(
        public int $created,
        public int $npcs,
        public int $skipped,
        public array $errors,
    ) {}
}
