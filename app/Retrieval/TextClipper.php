<?php

namespace App\Retrieval;

final class TextClipper
{
    public static function clip(string $text, int $maxCharacters): string
    {
        if (mb_strlen($text) <= $maxCharacters) {
            return $text;
        }

        return mb_substr($text, 0, $maxCharacters).'…';
    }
}
