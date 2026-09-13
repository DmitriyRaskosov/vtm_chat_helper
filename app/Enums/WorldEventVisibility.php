<?php

namespace App\Enums;

enum WorldEventVisibility: string
{
    case Public = 'public';
    case StorytellerOnly = 'storyteller_only';
}
