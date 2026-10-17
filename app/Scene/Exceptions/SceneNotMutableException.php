<?php

namespace App\Scene\Exceptions;

use App\Exceptions\ConflictException;

class SceneNotMutableException extends ConflictException
{
    public function __construct()
    {
        parent::__construct('Сцена закрыта или локация уже изменена');
    }
}
