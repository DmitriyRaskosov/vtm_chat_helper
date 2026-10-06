<?php

namespace App\Jobs;

use App\Diary\DiaryWriterService;
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

    public function handle(
        DiaryWriterService $writer,
        DiarySummarizerService $summarizer,
    ): void {
        $scene = Scene::query()->find($this->sceneId);

        if ($scene === null) {
            Log::warning('diary.summary.job.scene_missing', ['scene_id' => $this->sceneId]);

            return;
        }

        // 1. Досоздать L0 из сообщений, которые не попали ни в одну запись
        //    (остаток после последнего автоматического L0).
        $writeResult = $writer->writeForScene($scene);

        // 2. Собрать L1 из всех L0 этой сцены.
        $summaryResult = $summarizer->summarizeScene($scene);

        Log::info('diary.summary.job.done', [
            'scene_id' => $this->sceneId,
            'l0_created' => $writeResult['created'],
            'l0_processed_messages' => $writeResult['processed_messages'],
            'l1_created' => $summaryResult['created'],
            'npcs' => $summaryResult['npcs'],
            'errors' => array_merge($writeResult['errors'], $summaryResult['errors']),
        ]);
    }
}