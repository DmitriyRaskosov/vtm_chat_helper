<?php

namespace Tests\Unit\Context;

use App\Context\MessageWindowSelector;
use App\Context\TokenEstimator;
use App\Models\Message;
use Tests\TestCase;

class MessageWindowSelectorTest extends TestCase
{
    public function test_it_stops_at_fifty_whole_messages(): void
    {
        $messages = collect(range(1, 51))
            ->map(fn (int $id): Message => $this->message($id, 1));

        $window = $this->selector()->select($messages, 6000, 50);

        $this->assertSame(50, $window->messageCount());
        $this->assertSame(range(1, 50), $window->messages->pluck('id')->all());
        $this->assertSame(50, $window->tokenCount);
        $this->assertFalse($window->oversized);
    }

    public function test_message_that_does_not_fit_starts_the_next_window(): void
    {
        $messages = collect([
            $this->message(1, 5999),
            $this->message(2, 2),
        ]);

        $first = $this->selector()->select($messages, 6000, 50);
        $second = $this->selector()->select($messages->slice($first->messageCount()), 6000, 50);

        $this->assertSame([1], $first->messages->pluck('id')->all());
        $this->assertSame(5999, $first->tokenCount);
        $this->assertSame([2], $second->messages->pluck('id')->all());
    }

    public function test_message_that_exactly_fills_limit_is_included(): void
    {
        $window = $this->selector()->select([
            $this->message(1, 5999),
            $this->message(2, 1),
        ], 6000, 50);

        $this->assertSame([1, 2], $window->messages->pluck('id')->all());
        $this->assertSame(6000, $window->tokenCount);
    }

    public function test_single_oversized_message_is_kept_whole(): void
    {
        $window = $this->selector()->select([
            $this->message(1, 7000),
            $this->message(2, 1),
        ], 6000, 50);

        $this->assertSame([1], $window->messages->pluck('id')->all());
        $this->assertSame(7000, $window->tokenCount);
        $this->assertTrue($window->oversized);
    }

    private function selector(): MessageWindowSelector
    {
        return new MessageWindowSelector(new TokenEstimator);
    }

    private function message(int $id, int $tokens): Message
    {
        $message = new Message;
        $message->id = $id;
        $message->body = str_repeat('x', $tokens);
        $message->token_estimate = $tokens;
        $message->token_estimator_version = (new TokenEstimator)->version();

        return $message;
    }
}
