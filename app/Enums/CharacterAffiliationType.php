<?php

namespace App\Enums;

enum CharacterAffiliationType: string
{
    case Member = 'member';
    case Resident = 'resident';
    case Owner = 'owner';
    case Adherent = 'adherent';
    case Other = 'other';
}
