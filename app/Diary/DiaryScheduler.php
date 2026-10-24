<?php

namespace App\Diary;

use App\Jobs\SummarizeSceneDiaryJob;
use App\Jobs\WriteDiaryJob;
use App\Jobs\WriteFinalDiaryJob;
use App\Models\Character;
use App\Models\Message;
use App\Models\Scene;
use App\Models\SceneParticipant;
use Illuminate\Support\Facades\Log;

final class DiaryScheduler
{
    public function __construct(private DiaryStaleMarker $staleMarker) {}

    public function scheduleForScene(Scene $scene): void
    {
        $threshold = (int) config('diary.write_threshold', 15);
        if ($threshold <= 0) {
            return;
        }

        $participants = SceneParticipant::query()
            ->where('scene_id', $scene->id)
            ->where('is_current', true)
            ->get();

        if ($participants->isEmpty()) {
            return;
        }

        $minCursor = $participants->min(fn ($p) => (int) ($p->last_diary_message_id ?? 0));

        $pending = Message::query()
            ->where('scene_id', $scene->id)
            ->where('id', '>', $minCursor)
            ->where('is_ooc', false)
            ->count();

        if ($pending < $threshold) {
            return;
        }

        WriteDiaryJob::dispatch($scene->id)->afterCommit();

        Log::info('diary.write.dispatched', [
            'scene_id' => $scene->id,
            'pending' => $pending,
            'threshold' => $threshold,
        ]);
    }

    public function scheduleSummary(Scene $scene): void
    {
        SummarizeSceneDiaryJob::dispatch($scene->id)->afterCommit();
    }

    public function scheduleFinal(Scene $scene, Character $character): void
    {
        WriteFinalDiaryJob::dispatch($scene->id, $character->id)->afterCommit();
    }

    public function markStale(Message $message, string $reason): void
    {
        $affected = $this->staleMarker->markStaleCovering($message);

        if ($affected > 0) {
            Log::info('diary.marked_stale', [
                'message_id' => $message->id,
                'scene_id' => $message->scene_id,
                'reason' => $reason,
                'affected' => $affected,
            ]);
        }
    }
}
