<?php

namespace App\Enums;

enum CharacterKnowledgeLevel: string
{
    case Rumor = 'rumor';
    case Partial = 'partial';
    case Known = 'known';
    case Expert = 'expert';
}
