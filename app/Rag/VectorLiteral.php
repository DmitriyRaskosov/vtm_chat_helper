<?php

namespace App\Rag;

final class VectorLiteral
{
    /**
     * @param  list<float>  $vector
     */
    public static function fromArray(array $vector): string
    {
        return '['.implode(',', array_map(fn (float $v): string => (string) $v, $vector)).']';
    }
}
