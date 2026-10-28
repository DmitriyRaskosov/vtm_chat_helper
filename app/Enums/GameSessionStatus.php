<?php

namespace App\Enums;

enum GameSessionStatus: string
{
    case Active = 'active';
    case Archived = 'archived';
}
