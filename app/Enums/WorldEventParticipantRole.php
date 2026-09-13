<?php

namespace App\Enums;

enum WorldEventParticipantRole: string
{
    case Actor = 'actor';
    case Victim = 'victim';
    case Witness = 'witness';
    case Organizer = 'organizer';
    case Mentioned = 'mentioned';
    case Other = 'other';
}
