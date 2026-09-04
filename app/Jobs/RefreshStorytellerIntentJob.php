<?php

namespace App\Jobs;

use App\Context\StorytellerIntentManager;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RefreshStorytellerIntentJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 600;

    public int $timeout = 180;

    public int $tries = 3;

    public function __construct(
        public int $gameSessionId,
        public int $storytellerId,
    ) {}

    public function uniqueId(): string
    {
        return $this->gameSessionId.':'.$this->storytellerId;
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(StorytellerIntentManager $intents): void
    {
        $intents->refresh($this->gameSessionId, $this->storytellerId);
    }
}
