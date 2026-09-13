<?php

namespace App\Character;

use App\Enums\CharacterHealthDamage;
use App\Enums\CharacterHealthState;
use App\Models\Character;
use App\Models\CharacterHealthBox;
use App\Models\CharacterStatus;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CharacterHealthService
{
    /**
     * @param  list<array{index: int, damage: string|null}>  $boxes
     */
    public function replace(Character $character, array $boxes): void
    {
        $byIndex = [];

        foreach ($boxes as $box) {
            $index = (int) $box['index'];
            if ($index < 0 || $index > 6) {
                throw new InvalidArgumentException('A health box index must be between 0 and 6.');
            }

            $damage = $box['damage'] ?? null;
            if ($damage === '') {
                $damage = null;
            }

            if ($damage !== null) {
                CharacterHealthDamage::from((string) $damage);
            }

            $byIndex[$index] = $damage === null ? null : (string) $damage;
        }

        DB::transaction(function () use ($character, $byIndex): void {
            CharacterHealthBox::query()->where('character_id', $character->id)->delete();

            $worst = null;

            foreach ($byIndex as $index => $damage) {
                if ($damage === null) {
                    continue;
                }

                CharacterHealthBox::query()->create([
                    'character_id' => $character->id,
                    'box_index' => $index,
                    'damage' => $damage,
                ]);

                $worst = $worst === null ? $index : max($worst, $index);
            }

            $this->syncHealthState($character, CharacterHealthState::fromHealthBox($worst));
        });
    }

    private function syncHealthState(Character $character, CharacterHealthState $state): void
    {
        $status = CharacterStatus::query()->find($character->id);

        if ($status === null) {
            CharacterStatus::query()->create([
                'character_id' => $character->id,
                'chronicle_id' => $character->chronicle_id,
                'health_state' => $state,
                'revision' => 0,
            ]);

            return;
        }

        if ($status->health_state === $state) {
            return;
        }

        $status->health_state = $state;
        $status->save();
    }
}
