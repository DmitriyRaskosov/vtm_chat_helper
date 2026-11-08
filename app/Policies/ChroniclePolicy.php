<?php

namespace App\Policies;

use App\Models\Chronicle;
use App\Models\User;
use App\Policies\Concerns\GuardsChronicleMembership;
use Illuminate\Auth\Access\Response;

class ChroniclePolicy
{
    use GuardsChronicleMembership;

    public function view(User $user, Chronicle $chronicle): bool
    {
        $this->ensureActiveMemberOfChronicle($user, $chronicle);

        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Chronicle $chronicle): Response|bool
    {
        $this->ensureActiveMemberOfChronicle($user, $chronicle);

        return $user->isStorytellerIn($chronicle)
            ? true
            : Response::deny('Только рассказчик.');
    }

    public function viewInvitations(User $user, Chronicle $chronicle): bool
    {
        $this->ensureActiveMemberOfChronicle($user, $chronicle);

        return $user->isStorytellerIn($chronicle);
    }

    public function createInvitation(User $user, Chronicle $chronicle): Response|bool
    {
        $this->ensureActiveMemberOfChronicle($user, $chronicle);

        return $user->isStorytellerIn($chronicle)
            ? true
            : Response::deny('Только рассказчик.');
    }

    public function revokeInvitation(User $user, Chronicle $chronicle): Response|bool
    {
        $this->ensureActiveMemberOfChronicle($user, $chronicle);

        return $user->isStorytellerIn($chronicle)
            ? true
            : Response::deny('Только рассказчик.');
    }
}
