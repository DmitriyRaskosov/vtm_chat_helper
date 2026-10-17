<?php

namespace App\Policies;

use App\Models\GameSession;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ScenePolicy
{
    public function view(User $user, Scene $scene): bool
    {
        // этап 3: членство в хронике
        return true;
    }

    public function create(User $user, GameSession $gameSession): Response|bool
    {
        return $this->denyUnlessStoryteller($user);
    }

    public function update(User $user, Scene $scene): Response|bool
    {
        return $this->denyUnlessStoryteller($user);
    }

    public function activate(User $user, Scene $scene): Response|bool
    {
        return $this->denyUnlessStoryteller($user);
    }

    public function close(User $user, Scene $scene): Response|bool
    {
        return $this->denyUnlessStoryteller($user);
    }

    public function addParticipant(User $user, Scene $scene): Response|bool
    {
        return $this->denyUnlessStoryteller($user);
    }

    public function removeParticipant(User $user, Scene $scene): Response|bool
    {
        return $this->denyUnlessStoryteller($user);
    }

    public function createCopilotDraft(User $user, Scene $scene): Response|bool
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
