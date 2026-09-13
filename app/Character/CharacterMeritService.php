<?php

namespace App\Character;

use App\Enums\CharacterMeritKind;
use App\Models\Character;
use App\Models\CharacterMeritFlaw;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CharacterMeritService
{
    /**
     * @param  list<array{kind: string, name: string, cost: int, note?: string|null}>  $rows
     */
    public function replace(Character $character, array $rows): void
    {
        $prepared = [];

        foreach ($rows as $index => $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                throw new InvalidArgumentException('A merit or flaw name is required.');
            }

            $cost = (int) ($row['cost'] ?? 0);
            if ($cost < 0 || $cost > 10) {
                throw new InvalidArgumentException('A merit or flaw cost must be between 0 and 10.');
            }

            $prepared[] = [
                'character_id' => $character->id,
                'kind' => CharacterMeritKind::from((string) $row['kind'])->value,
                'name' => $name,
                'cost' => $cost,
                'note' => isset($row['note']) && is_string($row['note']) && trim($row['note']) !== ''
                    ? trim($row['note'])
                    : null,
                'sort_order' => $index,
            ];
        }

        DB::transaction(function () use ($character, $prepared): void {
            CharacterMeritFlaw::query()->where('character_id', $character->id)->delete();

            foreach ($prepared as $row) {
                CharacterMeritFlaw::query()->create($row);
            }
        });
    }
}
