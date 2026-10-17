<?php

namespace App\Policies;

use App\Models\CharacterDiaryEntry;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CharacterDiaryEntryPolicy
{
    public function viewAny(User $user): Response|bool
    {
        return $this->denyUnlessStoryteller($user);
    }

    public function view(User $user, CharacterDiaryEntry $diaryEntry): Response|bool
    {
        return $this->denyUnlessStoryteller($user);
    }

    public function update(User $user, CharacterDiaryEntry $diaryEntry): Response|bool
    {
        return $this->denyUnlessStoryteller($user);
    }

    public function delete(User $user, CharacterDiaryEntry $diaryEntry): Response|bool
    {
        return $this->denyUnlessStoryteller($user);
    }

    public function regenerate(User $user, CharacterDiaryEntry $diaryEntry): Response|bool
    {
        return $this->denyUnlessStoryteller($user);
    }

    private function denyUnlessStoryteller(User $user): Response|bool
    {
        return $user->isStoryteller()
            ? true
            : Response::deny('Только рассказчик.');
    }
}
