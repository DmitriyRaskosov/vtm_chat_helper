<?php

namespace App\Enums;

enum ConceptType: string
{
    case Tradition = 'tradition';
    case Principle = 'principle';
    case Doctrine = 'doctrine';
    case Other = 'other';
}
