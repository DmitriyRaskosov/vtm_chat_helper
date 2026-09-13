<?php

namespace App\Enums;

enum CharacterStatusEffectType: string
{
    case Penalty = 'penalty';
    case Bonus = 'bonus';
    case Wound = 'wound';
    case Temporary = 'temporary';
}
