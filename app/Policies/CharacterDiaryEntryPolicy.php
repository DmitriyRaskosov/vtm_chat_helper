<?php

namespace App\Policies;

use App\Chronicle\CurrentChronicle;
use App\Models\CharacterDiaryEntry;
use App\Models\Chronicle;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CharacterDiaryEntryPolicy
{
    public function __construct(
        private CurrentChronicle $currentChronicle,
    ) {}

    public function viewAny(User $user): Response|bool
    {
        return $this->denyUnlessStoryteller($user, $this->currentChronicle->get());
    }

    public function view(User $user, CharacterDiaryEntry $diaryEntry): Response|bool
    {
        return $this->denyUnlessStoryteller($user, $this->chronicleForEntry($diaryEntry));
    }

    public function update(User $user, CharacterDiaryEntry $diaryEntry): Response|bool
    {
        return $this->denyUnlessStoryteller($user, $this->chronicleForEntry($diaryEntry));
    }

    public function delete(User $user, CharacterDiaryEntry $diaryEntry): Response|bool
    {
        return $this->denyUnlessStoryteller($user, $this->chronicleForEntry($diaryEntry));
    }

    public function regenerate(User $user, CharacterDiaryEntry $diaryEntry): Response|bool
    {
        return $this->denyUnlessStoryteller($user, $this->chronicleForEntry($diaryEntry));
    }

    private function denyUnlessStoryteller(User $user, Chronicle $chronicle): Response|bool
    {
        return $user->isStorytellerIn($chronicle)
            ? true
            : Response::deny('Только рассказчик.');
    }

    private function chronicleForEntry(CharacterDiaryEntry $diaryEntry): Chronicle
    {
        return Chronicle::query()->findOrFail($diaryEntry->chronicle_id);
    }
}
