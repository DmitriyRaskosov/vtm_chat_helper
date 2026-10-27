<?php

namespace App\Policies;

use App\Models\Chronicle;
use App\Models\ChronicleMember;
use App\Models\GameSession;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ScenePolicy
{
    public function view(User $user, Scene $scene): Response|bool
    {
        return $this->denyUnlessActiveMember($user, $this->chronicleForScene($scene));
    }

    public function create(User $user, GameSession $gameSession): Response|bool
    {
        return $this->denyUnlessStoryteller($user, $this->chronicleForGameSession($gameSession));
    }

    public function update(User $user, Scene $scene): Response|bool
    {
        return $this->denyUnlessStoryteller($user, $this->chronicleForScene($scene));
    }

    public function activate(User $user, Scene $scene): Response|bool
    {
        return $this->denyUnlessStoryteller($user, $this->chronicleForScene($scene));
    }

    public function close(User $user, Scene $scene): Response|bool
    {
        return $this->denyUnlessStoryteller($user, $this->chronicleForScene($scene));
    }

    public function addParticipant(User $user, Scene $scene): Response|bool
    {
        return $this->denyUnlessStoryteller($user, $this->chronicleForScene($scene));
    }

    public function removeParticipant(User $user, Scene $scene): Response|bool
    {
        return $this->denyUnlessStoryteller($user, $this->chronicleForScene($scene));
    }

    public function createCopilotDraft(User $user, Scene $scene): Response|bool
    {
        return $this->denyUnlessStoryteller($user, $this->chronicleForScene($scene));
    }

    private function denyUnlessActiveMember(User $user, Chronicle $chronicle): Response|bool
    {
        return ChronicleMember::query()
            ->where('chronicle_id', $chronicle->id)
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->exists()
            ? true
            : Response::deny('Вы не участник этой хроники.');
    }

    private function denyUnlessStoryteller(User $user, Chronicle $chronicle): Response|bool
    {
        return $user->isStorytellerIn($chronicle)
            ? true
            : Response::deny('Только рассказчик.');
    }

    private function chronicleForScene(Scene $scene): Chronicle
    {
        $scene->loadMissing('gameSession.chronicle');

        return $scene->gameSession->chronicle;
    }

    private function chronicleForGameSession(GameSession $gameSession): Chronicle
    {
        $gameSession->loadMissing('chronicle');

        return $gameSession->chronicle;
    }
}
