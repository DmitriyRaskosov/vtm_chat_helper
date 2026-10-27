<?php

namespace App\Enums;

enum DiceRollOutcome: string
{
    case Success = 'success';
    case Failure = 'failure';
    case Botch = 'botch';
}
