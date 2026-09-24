<?php

namespace App\Enums;

enum UserRole: string
{
    case Player = 'player';
    case Storyteller = 'storyteller';
}
