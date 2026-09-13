<?php

namespace App\Enums;

enum LoreAccessLevel: string
{
    case L0 = '0';
    case L1 = '1';
    case L2 = '2';
    case L3 = '3';
    case L4 = '4';
    case L5 = '5';

    public function rank(): int
    {
        return (int) $this->value;
    }
}
