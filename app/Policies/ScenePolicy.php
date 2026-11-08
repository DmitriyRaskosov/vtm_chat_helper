<?php

namespace App\Policies;

use App\Models\Chronicle;
use App\Models\GameSession;
use App\Models\Scene;
use App\Models\User;
use App\Policies\Concerns\GuardsChronicleMembership;
use Illuminate\Auth\Access\Response;

class ScenePolicy
{
    use GuardsChronicleMembership;

    public function view(User $user, Scene $scene): bool
    {
        $this->ensureActiveMemberOfChronicle($user, $this->chronicleForScene($scene));

        return true;
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

    public function addParticipant(User $user, Scene $scene): bool
    {
        $this->ensureActiveMemberOfChronicle($user, $this->chronicleForScene($scene));

        return true;
    }

    public function removeParticipant(User $user, Scene $scene): bool
    {
        $this->ensureActiveMemberOfChronicle($user, $this->chronicleForScene($scene));

        return true;
    }

    public function createCopilotDraft(User $user, Scene $scene): Response|bool
    {
        return $this->denyUnlessStoryteller($user, $this->chronicleForScene($scene));
    }

    private function denyUnlessStoryteller(User $user, Chronicle $chronicle): Response|bool
    {
        $this->ensureActiveMemberOfChronicle($user, $chronicle);

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
