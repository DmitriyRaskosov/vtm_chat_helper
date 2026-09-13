<?php

namespace App\Enums;

enum ItemType: string
{
    case Weapon = 'weapon';
    case Relic = 'relic';
    case Document = 'document';
    case Mundane = 'mundane';
    case Other = 'other';
}
