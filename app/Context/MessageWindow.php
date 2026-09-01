<?php

namespace App\Context;

use App\Models\Message;
use Illuminate\Support\Collection;

final readonly class MessageWindow
{
    /**
     * @param  Collection<int, Message>  $messages
     */
    public function __construct(
        public Collection $messages,
        public int $tokenCount,
        public bool $oversized,
    ) {}

    public function messageCount(): int
    {
        return $this->messages->count();
    }

    public function isEmpty(): bool
    {
        return $this->messages->isEmpty();
    }
}
