<?php

namespace App\Enums;

enum LoreEntryKind: string
{
    case History = 'history';
    case Place = 'place';
    case Faction = 'faction';
    case Person = 'person';
    case Ritual = 'ritual';
    case Item = 'item';
    case Custom = 'custom';
}
