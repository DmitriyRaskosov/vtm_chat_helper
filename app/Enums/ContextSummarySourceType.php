<?php

namespace App\Enums;

enum ContextSummarySourceType: string
{
    case Message = 'message';
    case Summary = 'summary';
}
