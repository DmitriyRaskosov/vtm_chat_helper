<?php

namespace App\Policies;

use App\Models\Chronicle;
use App\Models\CombatEncounter;
use App\Models\Scene;
use App\Models\User;
use App\Policies\Concerns\GuardsChronicleMembership;
use Illuminate\Auth\Access\Response;

class CombatEncounterPolicy
{
    use GuardsChronicleMembership;

    public function viewAny(User $user, Scene $scene): bool
    {
        $this->ensureActiveMemberOfChronicle($user, $this->chronicleForScene($scene));

        return true;
    }

    public function view(User $user, CombatEncounter $encounter): bool
    {
        $this->ensureActiveMemberOfChronicle($user, $this->chronicleForEncounter($encounter));

        return true;
    }

    public function create(User $user, Scene $scene): Response|bool
    {
        return $this->denyUnlessStoryteller($user, $this->chronicleForScene($scene));
    }

    public function update(User $user, CombatEncounter $encounter): Response|bool
    {
        return $this->denyUnlessStoryteller($user, $this->chronicleForEncounter($encounter));
    }

    private function denyUnlessStoryteller(User $user, Chronicle $chronicle): Response|bool
    {
        $this->ensureActiveMemberOfChronicle($user, $chronicle);

        return $user->isStorytellerIn($chronicle)
            ? true
            : Response::deny('Только рассказчик.');
    }

    private function chronicleForEncounter(CombatEncounter $encounter): Chronicle
    {
        $encounter->loadMissing('scene.gameSession.chronicle');

        return $encounter->scene->gameSession->chronicle;
    }

    private function chronicleForScene(Scene $scene): Chronicle
    {
        $scene->loadMissing('gameSession.chronicle');

        return $scene->gameSession->chronicle;
    }
}
