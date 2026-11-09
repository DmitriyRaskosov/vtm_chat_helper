<?php

namespace App\Enums;

enum CombatEncounterStatus: string
{
    case Active = 'active';
    case Finished = 'finished';
}
