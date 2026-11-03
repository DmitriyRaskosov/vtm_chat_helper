<?php

namespace App\Character;

use App\Models\Character;
use App\Models\CharacterItem;
use Illuminate\Database\Eloquent\Collection;

final class CharacterItemService
{
    /**
     * @return Collection<int, CharacterItem>
     */
    public function list(Character $character): Collection
    {
        return $character->items()->orderBy('id')->get();
    }

    /**
     * @param  array{name: string, description?: string|null, quantity?: int, is_equipped?: bool}  $data
     */
    public function create(Character $character, array $data): CharacterItem
    {
        return $character->items()->create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'quantity' => $data['quantity'] ?? 1,
            'is_equipped' => $data['is_equipped'] ?? false,
        ]);
    }

    /**
     * @param  array{name?: string, description?: string|null, quantity?: int, is_equipped?: bool}  $data
     */
    public function update(CharacterItem $item, array $data): CharacterItem
    {
        $item->fill($data);
        $item->save();

        return $item;
    }

    public function delete(CharacterItem $item): void
    {
        $item->delete();
    }
}
