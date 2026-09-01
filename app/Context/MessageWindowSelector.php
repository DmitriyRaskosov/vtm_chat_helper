<?php

namespace App\Context;

use App\Models\Message;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class MessageWindowSelector
{
    public function __construct(private TokenEstimator $tokens) {}

    /**
     * Selects the oldest chronological prefix without splitting messages.
     *
     * @param  iterable<int, Message>  $messages
     */
    public function select(
        iterable $messages,
        ?int $maxTokens = null,
        ?int $maxMessages = null,
    ): MessageWindow {
        $tokenLimit = $maxTokens ?? (int) config('context.l0.max_tokens', 15000);
        $messageLimit = $maxMessages ?? (int) config('context.l0.max_messages', 50);

        if ($tokenLimit < 1 || $messageLimit < 1) {
            throw new InvalidArgumentException('Context window limits must be at least 1.');
        }

        /** @var Collection<int, Message> $selected */
        $selected = collect();
        $tokenCount = 0;
        $oversized = false;

        foreach ($messages as $message) {
            if ($selected->count() >= $messageLimit) {
                break;
            }

            $messageTokens = $message->token_estimate
                ?? $this->tokens->estimate($message->body);

            if ($selected->isNotEmpty() && $tokenCount + $messageTokens > $tokenLimit) {
                break;
            }

            $selected->push($message);
            $tokenCount += $messageTokens;

            if ($messageTokens > $tokenLimit) {
                $oversized = true;
                break;
            }
        }

        return new MessageWindow($selected, $tokenCount, $oversized);
    }
}
