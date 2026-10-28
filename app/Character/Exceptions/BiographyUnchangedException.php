<?php

namespace App\Character\Exceptions;

use App\Exceptions\InvalidInputException;

class BiographyUnchangedException extends InvalidInputException
{
    public function __construct()
    {
        parent::__construct('Biography is unchanged.');
    }
}
