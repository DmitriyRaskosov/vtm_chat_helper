<?php

namespace App\Enums;

enum ExtractionSourceType: string
{
    case Lore = 'lore';
    case Biography = 'biography';
    case Scene = 'scene';
}
