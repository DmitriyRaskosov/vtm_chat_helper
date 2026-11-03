<?php

namespace App\Character\Exceptions;

use App\Exceptions\InvalidInputException;

class PlayerCharacterLimitException extends InvalidInputException
{
    public function __construct()
    {
        parent::__construct('У игрока уже есть активный персонаж в этой хронике.');
    }
}
