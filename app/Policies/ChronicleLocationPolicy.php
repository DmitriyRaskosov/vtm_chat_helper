<?php

namespace App\Policies;

use App\Models\ChronicleLocation;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ChronicleLocationPolicy
{
    public function view(User $user, ChronicleLocation $location): Response|bool
    {
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
