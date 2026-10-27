<?php

namespace App\Observers;

use App\Context\TokenEstimator;
use App\Models\Message;

final class MessageObserver
{
    public function __construct(
        private TokenEstimator $estimator,
    ) {}

    public function saving(Message $message): void
    {
        if (! $message->isDirty('body') && $message->exists) {
            return;
        }

        $message->token_estimate = $this->estimator->estimate((string) $message->body);
        $message->token_estimator_version = $this->estimator->version();
    }
}
