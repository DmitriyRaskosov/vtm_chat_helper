<?php

namespace App\Enums;

enum RetrievalCorpus: string
{
    case Message = 'message';
    case Bio = 'bio';
    case Memory = 'memory';
    case Lore = 'lore';
    case Rules = 'rules';
    case Relation = 'relation';
}
