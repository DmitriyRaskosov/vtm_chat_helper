<?php

namespace App\Enums;

enum FactionType: string
{
    case Sect = 'sect';
    case Clan = 'clan';
    case Coterie = 'coterie';
    case Circle = 'circle';
    case Other = 'other';
}
