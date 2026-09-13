<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * @implements CastsAttributes<list<int>, list<int>>
 */
class PostgresIntegerArray implements CastsAttributes
{
    /**
     * @return list<int>
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_array($value)) {
            return array_values(array_map('intval', $value));
        }

        $trimmed = trim((string) $value, '{}');
        if ($trimmed === '') {
            return [];
        }

        return array_values(array_map('intval', explode(',', $trimmed)));
    }

    /**
     * @param  list<int>|mixed  $value
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if (! is_array($value)) {
            return '{0}';
        }

        $levels = array_values(array_unique(array_map('intval', $value)));
        sort($levels);

        if ($levels === []) {
            return '{}';
        }

        return '{'.implode(',', $levels).'}';
    }
}
