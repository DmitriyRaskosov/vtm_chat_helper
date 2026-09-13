<?php

namespace App\Enums;

enum WorldEntityType: string
{
    case Character = 'character';
    case Faction = 'faction';
    case Location = 'location';
    case Item = 'item';
    case Concept = 'concept';
    case Event = 'event';
}
