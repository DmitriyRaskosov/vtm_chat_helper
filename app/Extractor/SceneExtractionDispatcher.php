<?php

namespace App\Extractor;

use App\Enums\ExtractionRunStatus;
use App\Enums\ExtractionSourceType;
use App\Enums\ExtractionTrigger;
use App\Jobs\RunSceneExtractionJob;
use App\Models\Chronicle;
use App\Models\ExtractionRun;
use App\Models\Scene;
use App\Models\User;

class SceneExtractionDispatcher
{
    public function __construct(private SceneExtractionWindowPlanner $planner) {}

    public function maybeDispatchAfterMessage(Scene $scene, User $user): void
    {
        if ((string) config('extractor.driver') === 'none') {
            return;
        }

        $scene->loadMissing('gameSession');
        $chronicle = Chronicle::query()->findOrFail((int) $scene->gameSession->chronicle_id);
        $window = $this->planner->planNextWindow($scene, $chronicle, allowPartialTail: false);

        if ($window === null) {
            return;
        }

        $this->dispatchWindow($scene, $window, $user, ExtractionTrigger::Auto);
    }

    public function dispatchTailOnClose(Scene $scene, User $user): void
    {
        if ((string) config('extractor.driver') === 'none') {
            return;
        }

        $scene->loadMissing('gameSession');
        $chronicle = Chronicle::query()->findOrFail((int) $scene->gameSession->chronicle_id);
        $window = $this->planner->planNextWindow($scene, $chronicle, allowPartialTail: true);

        if ($window === null) {
            return;
        }

        $this->dispatchWindow($scene, $window, $user, ExtractionTrigger::Auto);
    }

    /**
     * @param  array{
     *     from_message_id: int,
     *     to_message_id: int,
     *     message_ids: list<int>
     * }  $window
     */
    public function dispatchWindow(
        Scene $scene,
        array $window,
        User $user,
        ExtractionTrigger $trigger,
        ?int $supersedesRunId = null,
    ): void {
        if ($this->hasActiveWindowRun($scene, $window['from_message_id'], $window['to_message_id'])) {
            return;
        }

        RunSceneExtractionJob::dispatch(
            $scene->id,
            $window['from_message_id'],
            $window['to_message_id'],
            $user->id,
            $trigger->value,
            $supersedesRunId,
        );
    }

    private function hasActiveWindowRun(Scene $scene, int $fromMessageId, int $toMessageId): bool
    {
        return ExtractionRun::query()
            ->where('source_type', ExtractionSourceType::Scene)
            ->where('source_id', $scene->id)
            ->where('from_message_id', $fromMessageId)
            ->where('to_message_id', $toMessageId)
            ->whereIn('status', ExtractionRunStatus::blockingSceneWindow())
            ->exists();
    }
}
