<?php

namespace App\Chronicle;

use App\Chronicle\Exceptions\NoChronicleException;
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

        if (is_string($chronicle) && ctype_digit($chronicle)) {
            $resolved = Chronicle::query()->find((int) $chronicle);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        throw new NoChronicleException;
    }
}
