<?php

namespace App\Enums;

enum CharacterStatCategory: string
{
    case Attribute = 'attribute';
    case Ability = 'ability';
    case Background = 'background';
    case Virtue = 'virtue';
    case Other = 'other';
}
