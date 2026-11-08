<?php

namespace App\Policies;

use App\Enums\ChronicleRole;
use App\Models\ChronicleMember;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PromptOverridePolicy
{
    public function viewAny(User $user): Response|bool
    {
        return $this->denyUnlessStoryteller($user);
    }

    public function update(User $user): Response|bool
    {
        return $this->denyUnlessStoryteller($user);
    }

    public function delete(User $user): Response|bool
    {
        return $this->denyUnlessStoryteller($user);
    }

    private function denyUnlessStoryteller(User $user): Response|bool
    {
        $isStoryteller = ChronicleMember::query()
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->where('role', ChronicleRole::Storyteller)
            ->exists();

        return $isStoryteller
            ? true
            : Response::deny('Только рассказчик.');
    }
}
