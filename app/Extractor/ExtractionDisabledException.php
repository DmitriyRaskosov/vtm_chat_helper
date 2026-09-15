<?php

namespace App\Extractor;

use RuntimeException;

class ExtractionDisabledException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Graph extractor is disabled.');
    }
}
