<?php

namespace App\Policies;

use App\Models\GameSession;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class GameSessionPolicy
{
    public function view(User $user): bool
    {
        // этап 3: членство в хронике
        return true;
    }

    public function create(User $user): Response|bool
    {
        return $this->denyUnlessStoryteller($user);
    }

    public function archive(User $user, GameSession $gameSession): Response|bool
    {
        return $this->denyUnlessStoryteller($user);
    }

    private function denyUnlessStoryteller(User $user): Response|bool
    {
        return $user->isStoryteller()
            ? true
            : Response::deny('Только рассказчик.');
    }
}
