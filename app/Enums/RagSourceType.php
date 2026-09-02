<?php

namespace App\Enums;

enum RagSourceType: string
{
    case Message = 'message';
    case Summary = 'summary';
    case Lore = 'lore';
    case Npc = 'npc';
    case Relationship = 'relationship';
}
