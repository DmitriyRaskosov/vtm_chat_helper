<?php

namespace App\Enums;

enum WorldEventStatus: string
{
    case Proposed = 'proposed';
    case Canonical = 'canonical';
    case Rejected = 'rejected';
}
