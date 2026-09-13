<?php

namespace App\Enums;

enum MemoryEntityRole: string
{
    case Subject = 'subject';
    case Mentioned = 'mentioned';
    case Place = 'place';
    case Opponent = 'opponent';
    case Witness = 'witness';
    case Other = 'other';
}
