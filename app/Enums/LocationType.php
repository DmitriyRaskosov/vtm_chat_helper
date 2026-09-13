<?php

namespace App\Enums;

enum LocationType: string
{
    case Region = 'region';
    case Settlement = 'settlement';
    case District = 'district';
    case Site = 'site';
    case Room = 'room';
}
