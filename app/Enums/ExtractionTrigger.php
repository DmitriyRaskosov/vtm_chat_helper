<?php

namespace App\Enums;

enum ExtractionTrigger: string
{
    case Auto = 'auto';
    case Manual = 'manual';
    case Reparse = 'reparse';
}
