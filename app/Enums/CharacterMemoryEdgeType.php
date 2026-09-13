<?php

namespace App\Enums;

enum CharacterMemoryEdgeType: string
{
    case CausedRecall = 'caused_recall';
    case SamePlace = 'same_place';
    case SamePerson = 'same_person';
    case Opponent = 'opponent';
    case ScentAssociation = 'scent_association';
    case EmotionalEcho = 'emotional_echo';
    case Consequence = 'consequence';
    case Contradiction = 'contradiction';
    case Reinforces = 'reinforces';
    case Precedes = 'precedes';
}
