<?php

namespace App\Chronicle;

use App\Chronicle\Exceptions\ChronicleNotFoundException;
use App\Models\Chronicle;
use Illuminate\Support\Facades\Route;

final class CurrentChronicle
{
    public function id(): int
    {
        return (int) $this->get()->id;
    }

    public function get(): Chronicle
    {
        $chronicle = Route::current()?->parameter('chronicle');

        if ($chronicle instanceof Chronicle) {
            return $chronicle;
        }

        throw new ChronicleNotFoundException;
    }
}
