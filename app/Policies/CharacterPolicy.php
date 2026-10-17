<?php

namespace App\Policies;

use App\Character\Exceptions\CharacterNotInChronicleException;
use App\Enums\CharacterType;
use App\Models\Character;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CharacterPolicy
{
    public function view(User $user, Character $character): Response|bool
    {
        return $this->denyUnlessManagesSheet($user, $character);
    }

    public function update(User $user, Character $character): Response|bool
    {
        return $this->denyUnlessManagesSheet($user, $character);
    }

    public function create(User $user): Response|bool
    {
        return $user->isStoryteller()
            ? true
            : Response::deny('Только рассказчик.');
    }

    public function archive(User $user, Character $character): Response|bool
    {
        return $user->isStoryteller()
            ? true
            : Response::deny('Только рассказчик.');
    }

    public function restore(User $user, Character $character): Response|bool
    {
        return $user->isStoryteller()
            ? true
            : Response::deny('Только рассказчик.');
    }

    public function updatePlace(User $user, Character $character): Response|bool
    {
        return $user->isStoryteller()
            ? true
            : Response::deny('Только рассказчик.');
    }

    public function speakAs(User $user, Character $character): bool
    {
        if (! $character->is_active) {
            return false;
        }

        return match ($character->character_type) {
            CharacterType::Npc => $user->isStoryteller(),
            CharacterType::Player => $character->isPlayableBy($user),
        };
    }

    public function viewInScene(User $user, Character $character, Scene $scene): bool
    {
        $scene->loadMissing('gameSession');

        if ((int) $character->chronicle_id !== (int) $scene->gameSession->chronicle_id) {
            throw new CharacterNotInChronicleException;
        }

        return $character->is_active;
    }

    private function denyUnlessManagesSheet(User $user, Character $character): Response|bool
    {
        if ($user->isStoryteller() || $character->isPlayableBy($user)) {
            return true;
        }

        return Response::deny('You cannot access this character sheet.');
    }
}
