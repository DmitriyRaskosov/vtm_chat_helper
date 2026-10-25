<?php

namespace App\Scene;

final class EventGeneratorService
{
    public function generate(
        string $actionDescription,
        int $attackSuccesses,
        bool $attackBotch,
        int $defenseSuccesses,
        bool $defenseBotch,
    ): string {
        return 'Сгенерировано: '.trim($actionDescription);
    }
}
