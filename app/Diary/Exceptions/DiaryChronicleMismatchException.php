<?php

namespace App\Diary\Exceptions;

use App\Exceptions\ConflictException;

class DiaryChronicleMismatchException extends ConflictException
{
    public function __construct()
    {
        parent::__construct('Diary entry must belong to the same chronicle as the scene.');
    }
}
