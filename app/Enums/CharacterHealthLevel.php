<?php

namespace App\Enums;

enum CharacterHealthLevel: int
{
    case Healthy = 0;
    case Bruised = 1;
    case Hurt = 2;
    case Injured = 3;
    case Wounded = 4;
    case Mauled = 5;
    case Crippled = 6;
    case Incapacitated = 7;

    public function label(): string
    {
        return match ($this) {
            self::Healthy => 'Healthy',
            self::Bruised => 'Bruised',
            self::Hurt => 'Hurt',
            self::Injured => 'Injured',
            self::Wounded => 'Wounded',
            self::Mauled => 'Mauled',
            self::Crippled => 'Crippled',
            self::Incapacitated => 'Incapacitated',
        };
    }
}
