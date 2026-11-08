<?php

namespace App\Policies;

use App\Models\ChronicleLocation;
use App\Models\User;
use App\Policies\Concerns\GuardsChronicleMembership;
use Illuminate\Auth\Access\Response;

class ChronicleLocationPolicy
{
    use GuardsChronicleMembership;

    public function view(User $user, ChronicleLocation $location): Response|bool
    {
        $this->ensureActiveMemberOfChronicle($user, $location->chronicle);

        return $user->isStorytellerIn($location->chronicle)
            ? true
            : Response::deny('Только рассказчик.');
    }

    public function update(User $user, ChronicleLocation $location): Response|bool
    {
        return $this->view($user, $location);
    }

    public function delete(User $user, ChronicleLocation $location): Response|bool
    {
        return $this->view($user, $location);
    }
}
