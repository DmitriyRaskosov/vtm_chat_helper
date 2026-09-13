<?php

namespace App\Enums;

enum LoreEntryStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Archived = 'archived';
}
