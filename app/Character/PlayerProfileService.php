<?php

namespace App\Character;

use App\Enums\CharacterType;
use App\Enums\ChronicleRole;
use App\Models\Character;
use App\Models\ChronicleMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class PlayerProfileService
{
    /**
     * @return Collection<int, Character>
     */
    public function charactersFor(User $user, bool $includeArchived = false): Collection
    {
        $storytellerChronicleIds = ChronicleMember::query()
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->where('role', ChronicleRole::Storyteller)
            ->pluck('chronicle_id');

        return Character::query()
            ->where(function ($query) use ($user, $storytellerChronicleIds, $includeArchived): void {
                $query->where(function ($players) use ($user, $includeArchived): void {
                    $players->where('user_id', $user->id)
                        ->where('character_type', CharacterType::Player);

                    if (! $includeArchived) {
                        $players->where('is_active', true);
                    }
                });

                if ($storytellerChronicleIds->isNotEmpty()) {
                    $query->orWhere(function ($npcs) use ($storytellerChronicleIds, $includeArchived): void {
                        $npcs->whereIn('chronicle_id', $storytellerChronicleIds)
                            ->where('character_type', CharacterType::Npc);

                        if (! $includeArchived) {
                            $npcs->where('is_active', true);
                        }
                    });
                }
            })
            ->with(['entity', 'chronicle'])
            ->orderBy('chronicle_id')
            ->orderBy('id')
            ->get();
    }
}
