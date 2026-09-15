<?php

namespace App\Jobs;

use App\Enums\ExtractionRunStatus;
use App\Enums\ExtractionSourceType;
use App\Enums\ExtractionTrigger;
use App\Extractor\GraphExtractorService;
use App\Models\Chronicle;
use App\Models\ExtractionRun;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunSceneExtractionJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $uniqueFor = 600;

    public function __construct(
        public int $sceneId,
        public int $fromMessageId,
        public int $toMessageId,
        public int $userId,
        public string $trigger,
        public ?int $supersedesRunId = null,
    ) {}

    public function uniqueId(): string
    {
        return "{$this->sceneId}:{$this->fromMessageId}:{$this->toMessageId}";
    }

    public function handle(GraphExtractorService $extractor): void
    {
        if ((string) config('extractor.driver') === 'none') {
            return;
        }

        $scene = Scene::query()->with('gameSession')->find($this->sceneId);
        $user = User::query()->find($this->userId);

        if ($scene === null || $user === null) {
            return;
        }

        $chronicle = Chronicle::query()->find((int) $scene->gameSession->chronicle_id);

        if ($chronicle === null) {
            return;
        }

        $existing = ExtractionRun::query()
            ->where('source_type', ExtractionSourceType::Scene)
            ->where('source_id', $scene->id)
            ->where('from_message_id', $this->fromMessageId)
            ->where('to_message_id', $this->toMessageId)
            ->whereIn('status', ExtractionRunStatus::blockingSceneWindow())
            ->exists();

        if ($existing) {
            return;
        }

        $trigger = ExtractionTrigger::tryFrom($this->trigger) ?? ExtractionTrigger::Auto;

        if ($this->supersedesRunId !== null) {
            $oldRun = ExtractionRun::query()->find($this->supersedesRunId);
            if ($oldRun !== null && $oldRun->status !== ExtractionRunStatus::Superseded) {
                $oldRun->update(['status' => ExtractionRunStatus::Superseded]);
            }
        }

        try {
            $extractor->runFromSceneWindow(
                $scene,
                $chronicle,
                $user,
                $this->fromMessageId,
                $this->toMessageId,
                $trigger,
            );
        } catch (\Throwable) {
            // Failed runs are persisted by GraphExtractorService.
        }
    }
}
