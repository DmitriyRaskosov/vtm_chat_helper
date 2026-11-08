<?php

namespace App\Prompt\Exceptions;

use App\Exceptions\NotFoundException;

final class PromptNotFoundException extends NotFoundException
{
    public function __construct(string $key)
    {
        parent::__construct("Prompt not found: {$key}");
    }
}
