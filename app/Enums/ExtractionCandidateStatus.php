<?php

namespace App\Enums;

enum ExtractionCandidateStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Merged = 'merged';
    case Discarded = 'discarded';
}
