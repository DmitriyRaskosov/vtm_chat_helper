<?php

namespace App\Chronicle\Exceptions;

use App\Exceptions\NotFoundException;

class ChronicleNotFoundException extends NotFoundException
{
    public function __construct()
    {
        parent::__construct('Chronicle not found.');
    }
}
