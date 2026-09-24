<?php

namespace App\Enums;

enum SceneStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Closed = 'closed';
}
