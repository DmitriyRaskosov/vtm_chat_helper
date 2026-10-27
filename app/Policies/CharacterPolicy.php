<?php

namespace App\Policies;

use App\Character\Exceptions\CharacterNotInChronicleException;
use App\Chronicle\CurrentChronicle;
use App\Enums\CharacterType;
use App\Enums\ChronicleRole;
use App\Models\Character;
use App\Models\ChronicleMember;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CharacterPolicy
{
    public function __construct(
        private CurrentChronicle $currentChronicle,
    ) {}

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
        return $user->isStorytellerIn($this->currentChronicle->get())
            ? true
            : Response::deny('Только рассказчик.');
    }

    public function archive(User $user, Character $character): Response|bool
    {
        return $this->denyUnlessStorytellerInCharacterChronicle($user, $character);
    }

    public function restore(User $user, Character $character): Response|bool
    {
        return $this->denyUnlessStorytellerInCharacterChronicle($user, $character);
    }

    public function updatePlace(User $user, Character $character): Response|bool
    {
        return $this->denyUnlessStorytellerInCharacterChronicle($user, $character);
    }

    public function updateState(User $user, Character $character): bool
    {
        if ($this->userIsStorytellerInChronicleId($user, (int) $character->chronicle_id)) {
            return true;
        }

        if ($character->character_type === CharacterType::Player && (int) $character->user_id === (int) $user->id) {
            return true;
        }

        return false;
    }

    public function speakAs(User $user, Character $character): bool
    {
        if (! $character->is_active) {
            return false;
        }

        return match ($character->character_type) {
            CharacterType::Npc => $this->userIsStorytellerInChronicleId($user, (int) $character->chronicle_id),
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

    public function viewDiary(User $user, Character $character): Response|bool
    {
        return $this->denyUnlessStorytellerInCharacterChronicle($user, $character);
    }

    public function previewDiary(User $user, Character $character): Response|bool
    {
        return $this->denyUnlessStorytellerInCharacterChronicle($user, $character);
    }

    private function denyUnlessManagesSheet(User $user, Character $character): Response|bool
    {
        if ($this->userIsStorytellerInChronicleId($user, (int) $character->chronicle_id) || $character->isPlayableBy($user)) {
            return true;
        }

        return Response::deny('You cannot access this character sheet.');
    }

    private function denyUnlessStorytellerInCharacterChronicle(User $user, Character $character): Response|bool
    {
        return $this->userIsStorytellerInChronicleId($user, (int) $character->chronicle_id)
            ? true
            : Response::deny('Только рассказчик.');
    }

    private function userIsStorytellerInChronicleId(User $user, int $chronicleId): bool
    {
        return ChronicleMember::query()
            ->where('chronicle_id', $chronicleId)
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->where('role', ChronicleRole::Storyteller)
            ->exists();
    }
}
