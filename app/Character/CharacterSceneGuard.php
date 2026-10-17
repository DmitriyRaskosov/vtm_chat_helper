<?php

namespace App\Character;

use App\Character\Exceptions\CharacterArchivedException;
use App\Character\Exceptions\CharacterNotInChronicleException;
use App\Models\Character;
use App\Models\Scene;

final class CharacterSceneGuard
{
    public function assertUsableIn(Character $character, Scene $scene): void
    {
        $scene->loadMissing('gameSession');

        if ((int) $character->chronicle_id !== (int) $scene->gameSession->chronicle_id) {
            throw new CharacterNotInChronicleException;
        }

        if (! $character->is_active) {
            throw new CharacterArchivedException;
        }
    }
}
