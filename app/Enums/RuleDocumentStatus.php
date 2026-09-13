<?php

namespace App\Enums;

enum RuleDocumentStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Archived = 'archived';
}
