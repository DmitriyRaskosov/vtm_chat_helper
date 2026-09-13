<?php

namespace App\Enums;

enum WorldEventType: string
{
    case Social = 'social';
    case Violence = 'violence';
    case Discovery = 'discovery';
    case Ritual = 'ritual';
    case Political = 'political';
    case Other = 'other';
}
