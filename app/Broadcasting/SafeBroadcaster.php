<?php

namespace App\Broadcasting;

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

final class SafeBroadcaster
{
    public static function send(ShouldBroadcast $event): void
    {
        try {
            broadcast($event);
        } catch (\Throwable) {
            // Reverb недоступен — HTTP-запрос не должен падать.
        }
    }
}
