<?php

namespace App\Enums;

enum RagSourceType: string
{
    case Message = 'message';
    case Lore = 'lore';
    case Npc = 'npc';
    case Relationship = 'relationship';
}
