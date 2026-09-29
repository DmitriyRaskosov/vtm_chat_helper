<?php

namespace App\Character;

use App\Models\Character;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CharacterTraitService
{
    /**
     * @param  list<array{key?: string, label: string, value: string, sort_order?: int}>  $traits
     */
    public function sync(Character $character, array $traits): void
    {
        DB::transaction(function () use ($character, $traits): void {
            $character->traits()->delete();

            $usedKeys = [];
            $sortOrder = 0;

            foreach ($traits as $row) {
                $value = trim((string) ($row['value'] ?? ''));
                if ($value === '') {
                    continue;
                }

                $key = trim((string) ($row['key'] ?? ''));
                if ($key === '') {
                    $key = Str::slug((string) ($row['label'] ?? 'trait'));
                    if ($key === '') {
                        continue;
                    }

                    $base = $key;
                    $i = 2;
                    while (isset($usedKeys[$key])) {
                        $key = $base.'-'.$i;
                        $i++;
                    }
                }

                if (isset($usedKeys[$key])) {
                    continue;
                }

                $key = Str::limit($key, 64, '');

                $character->traits()->create([
                    'key' => $key,
                    'label' => trim((string) $row['label']),
                    'value' => $value,
                    'sort_order' => (int) ($row['sort_order'] ?? $sortOrder),
                ]);

                $usedKeys[$key] = true;
                $sortOrder++;
            }
        });
    }
}
