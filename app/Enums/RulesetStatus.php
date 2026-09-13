<?php

namespace App\Enums;

enum RulesetStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
