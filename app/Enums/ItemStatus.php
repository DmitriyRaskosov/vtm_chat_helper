<?php

namespace App\Enums;

enum ItemStatus: string
{
    case Intact = 'intact';
    case Held = 'held';
    case Lost = 'lost';
    case Destroyed = 'destroyed';
}
