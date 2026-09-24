<?php

namespace App\Enums;

enum FactionStatus: string
{
    case Active = 'active';
    case Covert = 'covert';
    case Defunct = 'defunct';
}
