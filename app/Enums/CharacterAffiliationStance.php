<?php

namespace App\Enums;

enum CharacterAffiliationStance: string
{
    case Allied = 'allied';
    case Neutral = 'neutral';
    case Wary = 'wary';
    case Hostile = 'hostile';
}
