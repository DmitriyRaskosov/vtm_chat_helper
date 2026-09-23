<?php

namespace App\Character;

use App\Enums\CharacterType;
use App\Models\Character;
use App\Models\User;

final class CharacterAccess
{
    public static function canManageSheet(?User $user, Character $character): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->isStoryteller() || $character->isPlayableBy($user);
    }

    public static function canSpeakAs(?User $user, Character $character): bool
    {
        if ($user === null) {
            return false;
        }

        if (! $character->is_active) {
            return false;
        }

        if ($character->character_type === CharacterType::Npc) {
            return $user->isStoryteller();
        }

        if ($character->character_type === CharacterType::Player) {
            return $character->isPlayableBy($user);
        }

        return false;
    }

    public static function abortUnlessManagesSheet(?User $user, Character $character): void
    {
        abort_unless(self::canManageSheet($user, $character), 403, 'You cannot access this character sheet.');
    }
}
