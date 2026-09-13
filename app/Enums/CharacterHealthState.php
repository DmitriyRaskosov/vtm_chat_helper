<?php

namespace App\Enums;

enum CharacterHealthState: string
{
    case Healthy = 'healthy';
    case Bruised = 'bruised';
    case Hurt = 'hurt';
    case Injured = 'injured';
    case Wounded = 'wounded';
    case Mauled = 'mauled';
    case Crippled = 'crippled';
    case Incapacitated = 'incapacitated';
    case Torpor = 'torpor';

    public static function fromHealthBox(?int $worstIndex): self
    {
        return match ($worstIndex) {
            null => self::Healthy,
            0 => self::Bruised,
            1 => self::Hurt,
            2 => self::Injured,
            3 => self::Wounded,
            4 => self::Mauled,
            5 => self::Crippled,
            6 => self::Incapacitated,
            default => self::Healthy,
        };
    }
}
