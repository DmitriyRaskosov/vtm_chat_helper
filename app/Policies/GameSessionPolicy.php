<?php

namespace App\Policies;

use App\Chronicle\CurrentChronicle;
use App\Models\Character;
use App\Models\Chronicle;
use App\Models\GameSession;
use App\Models\User;
use App\Policies\Concerns\GuardsChronicleMembership;
use Illuminate\Auth\Access\Response;

class GameSessionPolicy
{
    use GuardsChronicleMembership;

    public function __construct(
        private CurrentChronicle $currentChronicle,
    ) {}

    public function view(User $user, GameSession $gameSession): bool
    {
        return $this->denyUnlessActiveMember($user, $this->chronicleForGameSession($gameSession));
    }

    public function create(User $user): Response|bool
    {
        return $this->denyUnlessStoryteller($user, $this->currentChronicle->get());
    }

    public function archive(User $user, GameSession $gameSession): Response|bool
    {
        return $this->denyUnlessStoryteller($user, $this->chronicleForGameSession($gameSession));
    }

    public function setTurn(User $user, GameSession $gameSession): Response|bool
    {
        return $this->denyUnlessStoryteller($user, $this->chronicleForGameSession($gameSession));
    }

    public function releaseTurn(User $user, GameSession $gameSession): Response|bool
    {
        $chronicle = $this->chronicleForGameSession($gameSession);
        $this->ensureActiveMemberOfChronicle($user, $chronicle);

        $activeId = $gameSession->active_character_id;
        if ($activeId === null) {
            return Response::deny('Сейчас никому не передан ход.');
        }

        $character = Character::query()->find($activeId);
        if ($character === null || ! $character->isPlayableBy($user)) {
            return Response::deny('Сейчас не ваш ход.');
        }

        return true;
    }

    private function denyUnlessActiveMember(User $user, Chronicle $chronicle): bool
    {
        $this->ensureActiveMemberOfChronicle($user, $chronicle);

        return true;
    }

    private function denyUnlessStoryteller(User $user, Chronicle $chronicle): Response|bool
    {
        $this->ensureActiveMemberOfChronicle($user, $chronicle);

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
