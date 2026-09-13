<?php

namespace App\Enums;

enum CharacterHealthDamage: string
{
    case Bashing = 'bashing';
    case Lethal = 'lethal';
    case Aggravated = 'aggravated';
}
