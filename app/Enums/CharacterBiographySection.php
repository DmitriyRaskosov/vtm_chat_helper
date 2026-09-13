<?php

namespace App\Enums;

enum CharacterBiographySection: string
{
    case Summary = 'summary';
    case FullText = 'full_text';
    case Principles = 'principles';
    case Motivation = 'motivation';
    case Fears = 'fears';
    case Desires = 'desires';
    case BehavioralRules = 'behavioral_rules';
}
