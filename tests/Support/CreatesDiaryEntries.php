<?php

namespace Tests\Support;

use App\Models\Character;
use App\Models\CharacterDiaryEntry;
use App\Models\Chronicle;
use App\Models\Scene;
use Illuminate\Support\Facades\DB;

trait CreatesDiaryEntries
{
    protected function createDiaryEntry(
        string $text,
        int $level = 0,
        ?int $sceneId = null,
        int $fromMessageId = 1,
        int $toMessageId = 1,
        bool $isStale = false,
        ?string $embeddingSourceText = null,
        ?float $importance = null,
    ): CharacterDiaryEntry {
        /** @var Character $npc */
        $npc = $this->npc;
        /** @var Chronicle $chronicle */
        $chronicle = $this->chronicle;
        /** @var Scene $scene */
        $scene = $this->scene;

        if ($embeddingSourceText !== null) {
            $fake = new FakeEmbeddingProvider;
            $components = $fake->embed($embeddingSourceText);
            $vector = '['.implode(',', array_map(fn (float $v): string => (string) $v, $components)).']';
        } else {
            $vector = '['.implode(',', array_fill(0, 1024, '0.1')).']';
        }

        $id = DB::table('character_diary_entries')->insertGetId([
            'character_id' => $npc->id,
            'chronicle_id' => $chronicle->id,
            'scene_id' => $sceneId ?? $scene->id,
            'level' => $level,
            'from_message_id' => $fromMessageId,
            'to_message_id' => $toMessageId,
            'entry' => $text,
            'importance' => $importance,
            'embedding' => DB::raw("'{$vector}'::vector"),
            'is_stale' => $isStale,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return CharacterDiaryEntry::query()->findOrFail($id);
    }
}
