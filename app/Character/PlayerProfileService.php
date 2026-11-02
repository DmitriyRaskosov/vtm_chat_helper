<?php

namespace App\Character;

use App\Enums\CharacterType;
use App\Models\Character;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class PlayerProfileService
{
    /**
     * @return Collection<int, Character>
     */
    public function charactersFor(User $user): Collection
    {
        return Character::query()
            ->where('user_id', $user->id)
            ->where('character_type', CharacterType::Player)
            ->with('entity')
            ->orderBy('id')
            ->get();
    }
}
