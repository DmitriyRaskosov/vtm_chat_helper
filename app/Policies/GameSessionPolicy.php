<?php

namespace App\Policies;

use App\Chronicle\CurrentChronicle;
use App\Models\Chronicle;
use App\Models\GameSession;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class GameSessionPolicy
{
    public function __construct(
        private CurrentChronicle $currentChronicle,
    ) {}

    public function view(User $user): bool
    {
        // этап 3: членство в хронике
        return true;
    }

    public function create(User $user): Response|bool
    {
        return $this->denyUnlessStoryteller($user, $this->currentChronicle->get());
    }

    public function archive(User $user, GameSession $gameSession): Response|bool
    {
        return $this->denyUnlessStoryteller($user, $this->chronicleForGameSession($gameSession));
    }

    private function denyUnlessStoryteller(User $user, Chronicle $chronicle): Response|bool
    {
        return $user->isStorytellerIn($chronicle)
            ? true
            : Response::deny('Только рассказчик.');
    }

    private function chronicleForGameSession(GameSession $gameSession): Chronicle
    {
        $gameSession->loadMissing('chronicle');

        return $gameSession->chronicle;
    }
}
