<?php

namespace App\Enums;

enum SceneParticipantRole: string
{
    case Npc = 'npc';
    case Player = 'player';
    case Extra = 'extra';
}
