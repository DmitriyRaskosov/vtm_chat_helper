<?php

namespace App\Dice;

use App\Models\DiceMacro;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

final class DiceMacroService
{
    /**
     * @return Collection<int, DiceMacro>
     */
    public function list(User $user): Collection
    {
        return DiceMacro::query()
            ->where('user_id', $user->id)
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array{name: string, pool?: int|null, difficulty: int, dice_type?: int, use_willpower?: bool, stats?: list<string>}  $data
     */
    public function create(User $user, array $data): DiceMacro
    {
        return DiceMacro::query()->create([
            'user_id' => $user->id,
            'name' => $data['name'],
            'pool' => $this->poolValue($data['pool'] ?? null),
            'difficulty' => $data['difficulty'],
            'dice_type' => $data['dice_type'] ?? 10,
            'use_willpower' => $data['use_willpower'] ?? false,
            'stats' => $this->statNames($data['stats'] ?? []),
        ]);
    }

    /**
     * @param  array{name?: string, pool?: int|null, difficulty?: int, dice_type?: int, use_willpower?: bool, stats?: list<string>}  $data
     */
    public function update(DiceMacro $macro, array $data): DiceMacro
    {
        if (array_key_exists('pool', $data)) {
            $data['pool'] = $this->poolValue($data['pool']);
        }

        if (array_key_exists('stats', $data)) {
            $data['stats'] = $this->statNames($data['stats']);
        }

        $macro->fill($data);
        $macro->save();

        return $macro;
    }

    public function delete(DiceMacro $macro): void
    {
        $macro->delete();
    }

    private function poolValue(mixed $pool): int
    {
        if ($pool === null || $pool === '') {
            return 0;
        }

        return (int) $pool;
    }

    /**
     * @return list<string>
     */
    private function statNames(mixed $stats): array
    {
        if (is_string($stats)) {
            $stats = explode(',', $stats);
        }
        if (! is_array($stats)) {
            return [];
        }

        $names = [];
        foreach ($stats as $name) {
            if (! is_string($name)) {
                continue;
            }
            foreach (explode(',', $name) as $part) {
                $part = trim($part);
                if ($part !== '' && ! in_array($part, $names, true)) {
                    $names[] = $part;
                }
            }
        }

        return $names;
    }
}
