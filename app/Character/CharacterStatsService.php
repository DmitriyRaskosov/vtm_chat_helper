<?php

namespace App\Character;

use App\Models\Character;

final class CharacterStatsService
{
    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'blood_pool' => self::dotTrack(10, 10),
            'willpower' => self::dotTrack(5, 10),
            'health' => [
                'level' => 0,
                'damage' => 'bashing',
                'penalty' => 0,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $stats
     * @return array<string, mixed>
     */
    public static function normalize(array $stats): array
    {
        $normalized = [];

        foreach ($stats as $key => $value) {
            if (in_array($key, ['health', 'health_level', 'health_damage', 'health_penalty', 'temporary_willpower'], true)) {
                continue;
            }

            if ($key === 'disciplines') {
                $normalized['disciplines'] = self::normalizeDisciplines($value);

                continue;
            }

            $normalized[$key] = self::dotTrack($value, self::maxDots((string) $key));
        }

        if (array_key_exists('temporary_willpower', $stats)) {
            if (! array_key_exists('willpower', $stats)) {
                $normalized['willpower'] = self::dotTrack($stats['temporary_willpower'], 10);
            } else {
                $normalized['temporary_willpower'] = self::dotTrack($stats['temporary_willpower'], 10);
            }
        }

        $normalized['health'] = self::normalizeHealth($stats);

        return $normalized;
    }

    /**
     * 0 — пустая, 1 — постоянная, 2 — временная.
     *
     * @return list<int>
     */
    public static function dotTrack(mixed $value, int $max): array
    {
        $max = max(0, $max);

        if (is_array($value) && array_is_list($value)) {
            $dots = [];
            foreach ($value as $dot) {
                $state = (int) $dot;
                $dots[] = $state === 1 || $state === 2 ? $state : 0;
            }

            return self::fitDots($dots, $max);
        }

        $current = 0;
        $temporary = false;
        if (is_array($value) && array_key_exists('current', $value)) {
            $current = (int) $value['current'];
            $temporary = (bool) ($value['is_temporary'] ?? false);
        } elseif (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
            $current = (int) $value;
        }

        $current = max(0, min($max, $current));
        $dots = array_fill(0, $max, 0);
        for ($i = 0; $i < $current; $i++) {
            $dots[$i] = $temporary && $i === $current - 1 ? 2 : 1;
        }

        return $dots;
    }

    public static function filledDots(mixed $value, int $default): int
    {
        if (is_array($value) && array_is_list($value)) {
            return count(array_filter(
                $value,
                fn (mixed $dot): bool => (int) $dot > 0,
            ));
        }

        if (is_array($value) && array_key_exists('current', $value)) {
            return max(0, (int) $value['current']);
        }

        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
            return max(0, (int) $value);
        }

        return $default;
    }

    public static function maxDots(string $key): int
    {
        return in_array($key, ['blood_pool', 'willpower', 'temporary_willpower', 'humanity'], true) ? 10 : 5;
    }

    /**
     * @return array{backgrounds: list<array{name: string, value: int|string}>, merits_flaws: list<array{name: string, points: int, type: string}>, weakness: string}
     */
    public static function emptySheetData(): array
    {
        return [
            'backgrounds' => [],
            'merits_flaws' => [],
            'weakness' => '',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     backgrounds: list<array{name: string, value: int|string}>,
     *     merits_flaws: list<array{name: string, points: int, type: string}>,
     *     weakness: string,
     *     traits?: list<array{name: string, label: string, value: string}>,
     *     description?: string|null,
     *     inventory?: string|null
     * }
     */
    public static function normalizeSheetData(array $data): array
    {
        $backgrounds = [];
        foreach ($data['backgrounds'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $value = $row['value'] ?? '';
            $backgrounds[] = [
                'name' => (string) ($row['name'] ?? ''),
                'value' => is_int($value) || (is_string($value) && is_numeric($value)) ? (int) $value : (string) $value,
            ];
        }

        $merits = [];
        foreach ($data['merits_flaws'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $merits[] = [
                'name' => (string) ($row['name'] ?? ''),
                'points' => (int) ($row['points'] ?? 0),
                'type' => (string) ($row['type'] ?? ''),
            ];
        }

        $normalized = [
            'backgrounds' => $backgrounds,
            'merits_flaws' => $merits,
            'weakness' => (string) ($data['weakness'] ?? ''),
        ];

        if (array_key_exists('traits', $data) && is_array($data['traits'])) {
            $traits = [];
            foreach ($data['traits'] as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $traits[] = [
                    'name' => (string) ($row['name'] ?? ''),
                    'label' => (string) ($row['label'] ?? ''),
                    'value' => (string) ($row['value'] ?? ''),
                ];
            }
            $normalized['traits'] = $traits;
        }

        if (array_key_exists('description', $data)) {
            $normalized['description'] = self::nullableSheetString($data['description']);
        }

        if (array_key_exists('inventory', $data)) {
            $normalized['inventory'] = self::nullableSheetString($data['inventory']);
        }

        return $normalized;
    }

    private static function nullableSheetString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    public function replace(Character $character, array $stats, ?array $sheetData = null): Character
    {
        $character->stats = self::normalize($stats);
        if ($sheetData !== null) {
            $existing = is_array($character->sheet_data) ? $character->sheet_data : [];
            foreach (['traits', 'description', 'inventory'] as $key) {
                if (! array_key_exists($key, $sheetData) && array_key_exists($key, $existing)) {
                    $sheetData[$key] = $existing[$key];
                }
            }
            $character->sheet_data = self::normalizeSheetData($sheetData);
        }
        $character->save();

        return $character;
    }

    /**
     * @param  array<string, mixed>|null  $stats
     * @return array{
     *     blood_pool: int,
     *     temporary_willpower: int,
     *     health_level: int,
     *     health_damage: string,
     *     health_penalty: int
     * }
     */
    public static function legacyState(?array $stats): array
    {
        $stats ??= [];
        $level = self::healthLevel($stats);
        $penalty = self::healthPenaltyValue($stats, $level);

        return [
            'blood_pool' => self::filledDots($stats['blood_pool'] ?? null, 10),
            'temporary_willpower' => self::filledDots(
                $stats['willpower'] ?? ($stats['temporary_willpower'] ?? null),
                5,
            ),
            'health_level' => $level,
            'health_damage' => self::healthDamage($stats),
            'health_penalty' => $penalty,
        ];
    }

    public static function healthPenalty(int $healthLevel): int
    {
        return match ($healthLevel) {
            0, 1 => 0,
            2, 3 => -1,
            4, 5 => -2,
            6 => -5,
            7 => -100,
            default => 0,
        };
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private static function healthLevel(array $stats): int
    {
        $health = $stats['health'] ?? null;
        if (is_array($health) && array_key_exists('level', $health)) {
            return (int) $health['level'];
        }

        return self::filledDots($stats['health_level'] ?? null, 0);
    }

    /**
     * @param  array<string, mixed>  $stats
     * @return array{level: int, damage: string, penalty: int}
     */
    private static function normalizeHealth(array $stats): array
    {
        $health = $stats['health'] ?? null;
        if (is_array($health) && array_key_exists('level', $health)) {
            $level = (int) $health['level'];

            return [
                'level' => $level,
                'damage' => is_string($health['damage'] ?? null) && $health['damage'] !== ''
                    ? $health['damage']
                    : 'bashing',
                'penalty' => array_key_exists('penalty', $health)
                    ? (int) $health['penalty']
                    : self::healthPenalty($level),
            ];
        }

        $level = self::filledDots($stats['health_level'] ?? null, 0);
        $damage = $stats['health_damage'] ?? 'bashing';

        return [
            'level' => $level,
            'damage' => is_string($damage) && $damage !== '' ? $damage : 'bashing',
            'penalty' => array_key_exists('health_penalty', $stats)
                ? (int) $stats['health_penalty']
                : self::healthPenalty($level),
        ];
    }

    /**
     * @return array<string, list<int>>
     */
    private static function normalizeDisciplines(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $disciplines = [];
        foreach ($value as $slug => $dots) {
            if (! is_string($slug) || $slug === '') {
                continue;
            }

            $disciplines[$slug] = self::dotTrack($dots, 5);
        }

        return $disciplines;
    }

    /**
     * @param  list<int>  $dots
     * @return list<int>
     */
    private static function fitDots(array $dots, int $max): array
    {
        $dots = array_slice($dots, 0, $max);
        while (count($dots) < $max) {
            $dots[] = 0;
        }

        return $dots;
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private static function healthDamage(array $stats): string
    {
        $health = $stats['health'] ?? null;
        if (is_array($health) && isset($health['damage']) && is_string($health['damage']) && $health['damage'] !== '') {
            return $health['damage'];
        }

        $damage = $stats['health_damage'] ?? 'bashing';

        return is_string($damage) && $damage !== '' ? $damage : 'bashing';
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private static function healthPenaltyValue(array $stats, int $level): int
    {
        $health = $stats['health'] ?? null;
        if (is_array($health) && array_key_exists('penalty', $health)) {
            return (int) $health['penalty'];
        }

        if (array_key_exists('health_penalty', $stats)) {
            return (int) $stats['health_penalty'];
        }

        return self::healthPenalty($level);
    }
}
