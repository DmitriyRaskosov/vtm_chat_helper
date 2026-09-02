<?php

namespace App\Jobs;

use App\Context\SummaryManager;
use App\Enums\SceneStatus;
use App\Models\Scene;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FinalizeSceneContextJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 600;

    public int $timeout = 300;

    public int $tries = 3;

    public bool $failOnTimeout = true;

    public function __construct(public int $sceneId) {}

    public function uniqueId(): string
    {
        return (string) $this->sceneId;
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(SummaryManager $summaries): void
    {
        $scene = Scene::query()->find($this->sceneId);
        if ($scene === null || $scene->status !== SceneStatus::Closed) {
            return;
        }

        $summaries->finalizeScene($this->sceneId);
    }
}
