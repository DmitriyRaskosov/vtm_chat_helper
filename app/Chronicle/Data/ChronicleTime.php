<?php

namespace App\Chronicle\Data;

use Illuminate\Support\Carbon;

final readonly class ChronicleTime
{
    public function __construct(
        public Carbon $currentInGameDate,
        public ?int $activeSceneId,
        public ?Carbon $startedAtInGame,
    ) {}
}
