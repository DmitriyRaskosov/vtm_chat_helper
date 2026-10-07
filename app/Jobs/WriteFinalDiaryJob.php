<?php

namespace App\Jobs;

use App\Diary\DiaryWriterService;
use App\Models\Character;
use App\Models\Scene;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class WriteFinalDiaryJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 540;
    public int $uniqueFor = 900;

    public function __construct(
        public int $sceneId,
        public int $characterId,
    ) {}

    public function uniqueId(): string
    {
        return 'diary-final-'.$this->sceneId.'-'.$this->characterId;
    }

    public function handle(DiaryWriterService $service): void
    {
        $scene = Scene::query()->find($this->sceneId);
        $character = Character::query()->find($this->characterId);

        if ($scene === null || $character === null) {
            Log::warning('diary.final.job.missing', [
                'scene_id' => $this->sceneId,
                'character_id' => $this->characterId,
            ]);

            return;
        }

        $created = $service->writeFinalForNpc($scene, $character);

        Log::info('diary.final.job.done', [
            'scene_id' => $this->sceneId,
            'character_id' => $this->characterId,
            'created' => $created,
        ]);
    }
}