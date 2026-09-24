<?php

namespace App\Enums;

enum LoreCategory: string
{
    case History = 'history';
    case Politics = 'politics';
    case Geography = 'geography';
    case Society = 'society';
    case Clans = 'clans';
    case Creatures = 'creatures';
    case Metaplot = 'metaplot';
    case Misc = 'misc';
}
