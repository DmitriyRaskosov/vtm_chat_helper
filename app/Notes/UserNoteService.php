<?php

namespace App\Notes;

use App\Character\Exceptions\CharacterNotInChronicleException;
use App\Models\Character;
use App\Models\Chronicle;
use App\Models\User;
use App\Models\UserNote;

final class UserNoteService
{
    public function getNote(User $user, Character $character, Chronicle $chronicle): ?UserNote
    {
        $this->ensureCharacterInChronicle($character, $chronicle);

        return UserNote::query()
            ->where('user_id', $user->id)
            ->where('character_id', $character->id)
            ->where('chronicle_id', $chronicle->id)
            ->first();
    }

    public function saveNote(User $user, Character $character, Chronicle $chronicle, string $body): UserNote
    {
        $this->ensureCharacterInChronicle($character, $chronicle);

        return UserNote::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'character_id' => $character->id,
                'chronicle_id' => $chronicle->id,
            ],
            ['body' => $body],
        );
    }

    private function ensureCharacterInChronicle(Character $character, Chronicle $chronicle): void
    {
        if ((int) $character->chronicle_id !== (int) $chronicle->id) {
            throw new CharacterNotInChronicleException;
        }
    }
}
