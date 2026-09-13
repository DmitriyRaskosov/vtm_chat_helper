<?php

namespace App\World;

final class AliasNormalizer
{
    public static function normalize(string $value): string
    {
        $value = trim($value);
        $value = mb_strtolower($value, 'UTF-8');
        $normalized = preg_replace('/\s+/u', ' ', $value);

        return $normalized === null ? $value : $normalized;
    }

    public static function slug(string $value): string
    {
        $value = self::normalize($value);
        $value = str_replace(' ', '-', $value);
        $slug = preg_replace('/[^\p{L}\p{N}\-]+/u', '', $value);
        $slug = $slug === null ? '' : trim($slug, '-');

        return $slug === '' ? 'entity' : $slug;
    }
}
