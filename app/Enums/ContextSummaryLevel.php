<?php

namespace App\Enums;

enum ContextSummaryLevel: string
{
    case L0 = 'l0';
    case L1 = 'l1';
    case SceneFinal = 'scene_final';
    case Session = 'session';
}
