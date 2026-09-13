<?php

namespace App\Enums;

enum CharacterMemoryNodeType: string
{
    case Event = 'event';
    case Person = 'person';
    case Place = 'place';
    case Object = 'object';
    case Emotion = 'emotion';
    case Conclusion = 'conclusion';
    case Promise = 'promise';
    case Trauma = 'trauma';
    case Rumor = 'rumor';
}
