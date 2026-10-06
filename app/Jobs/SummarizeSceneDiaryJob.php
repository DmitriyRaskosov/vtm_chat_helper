<?php

namespace App\Jobs;

use App\Diary\DiarySummarizerService;
use App\Models\Scene;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SummarizeSceneDiaryJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 540;

    public int $uniqueFor = 900;

    public function __construct(public int $sceneId) {}

    public function uniqueId(): string
    {
        return 'diary-summary-scene-'.$this->sceneId;
    }

    public function handle(DiarySummarizerService $service): void
    {
        $scene = Scene::query()->find($this->sceneId);

        if ($scene === null) {
            Log::warning('diary.summary.job.scene_missing', ['scene_id' => $this->sceneId]);

            return;
        }

        $result = $service->summarizeScene($scene);

        Log::info('diary.summary.job.done', [
            'scene_id' => $this->sceneId,
            'created' => $result['created'],
            'npcs' => $result['npcs'],
            'skipped' => $result['skipped'],
            'errors' => count($result['errors']),
        ]);
    }
}