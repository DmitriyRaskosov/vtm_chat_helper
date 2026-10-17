<?php

namespace App\Chronicle;

use App\Chronicle\Exceptions\NoChronicleException;
use App\Models\Chronicle;

final class CurrentChronicle
{
    public function id(): int
    {
        return (int) $this->get()->id;
    }

    public function get(): Chronicle
    {
        $chronicle = Chronicle::query()->orderBy('id')->first();

        if ($chronicle === null) {
            throw new NoChronicleException;
        }

        return $chronicle;
    }
}
