<?php

namespace App\Context;

use App\Models\Character;
use App\Models\Chronicle;
use App\Models\GameSession;
use App\Models\Scene;
use App\Models\WorldEntity;

final readonly class ContextAssembly
{
    public function __construct(
        public ContextRequest $request,
        public Scene $scene,
        public GameSession $gameSession,
        public Chronicle $chronicle,
        public ?Character $character = null,
        public ?WorldEntity $entity = null,
    ) {}
}
