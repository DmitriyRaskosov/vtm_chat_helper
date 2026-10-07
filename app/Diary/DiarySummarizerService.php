<?php

namespace App\Diary;

use App\Enums\CharacterType;
use App\Llm\ChatProvider;
use App\Models\Character;
use App\Models\CharacterDiaryEntry;
use App\Models\Scene;
use App\Models\WorldEntity;
use App\Prompt\PromptRepository;
use App\Rag\EmbeddingProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class DiarySummarizerService
{
    private const MAX_L0_ENTRIES = 30;

    public function __construct(
        private ChatProvider $chat,
        private EmbeddingProvider $embeddings,
        private PromptRepository $prompts,
    ) {}

    /**
     * @return array{created: int, npcs: int, skipped: int, errors: list<string>}
     */
    public function summarizeScene(Scene $scene): array
    {
        $npcs = $this->resolveSceneNpcs($scene);
        if ($npcs->isEmpty()) {
            return ['created' => 0, 'npcs' => 0, 'skipped' => 0, 'errors' => []];
        }

        $created = 0;
        $skipped = 0;
        $errors = [];

        foreach ($npcs as $npc) {
            try {
                $result = $this->summarizeForNpc($npc, $scene);

                if ($result) {
                    $created++;
                } else {
                    $skipped++;
                }
            } catch (\Throwable $e) {
                $errors[] = 'NPC '.$npc->id.': '.$e->getMessage();
                Log::error('diary.summarize.npc_failed', [
                    'npc_id' => $npc->id,
                    'scene_id' => $scene->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'created' => $created,
            'npcs' => $npcs->count(),
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    private function summarizeForNpc(Character $npc, Scene $scene): bool
    {
        $l0Entries = CharacterDiaryEntry::query()
            ->where('character_id', $npc->id)
            ->where('scene_id', $scene->id)
            ->where('level', 0)
            ->orderBy('created_at')
            ->limit(self::MAX_L0_ENTRIES)
            ->get();

        if ($l0Entries->isEmpty()) {
            return false;
        }

        $existingL1 = CharacterDiaryEntry::query()
            ->where('character_id', $npc->id)
            ->where('scene_id', $scene->id)
            ->where('level', 1)
            ->exists();

        if ($existingL1) {
            Log::info('diary.summarize.already_exists', [
                'npc_id' => $npc->id,
                'scene_id' => $scene->id,
            ]);

            return false;
        }

        $npc->loadMissing(['clan', 'sect', 'traits']);

        $name = WorldEntity::query()->whereKey($npc->id)->value('canonical_name') ?? 'NPC';
        $clan = $npc->clan?->name ?? '—';
        $sect = $npc->sect?->name ?? '—';

        $traits = $npc->traits
            ->map(fn ($t): string => "- {$t->label}: {$t->value}")
            ->implode("\n");
        if ($traits === '') {
            $traits = '(нет)';
        }

        $entriesText = $l0Entries
            ->map(fn (CharacterDiaryEntry $e, int $i): string => '[entry '.($i + 1)."]\n".trim($e->entry))
            ->implode("\n\n");

        try {
            $turn = $this->chat->chatTurn(
                [
                    ['role' => 'system', 'content' => $this->prompts->get('diary.summarizer.system')],
                    ['role' => 'user', 'content' => $this->prompts->get('diary.summarizer.user', [
                        'name' => $name,
                        'clan' => $clan,
                        'sect' => $sect,
                        'traits' => $traits,
                        'entriesText' => $entriesText,
                    ])],
                ],
                ['max_tokens' => 500, 'temperature' => 0.4],
                [],
            );
        } catch (\Throwable $e) {
            throw new RuntimeException('LLM call failed: '.$e->getMessage(), 0, $e);
        }

        $entry = trim($turn->content);
        if ($entry === '') {
            throw new RuntimeException('LLM returned empty L1 entry.');
        }

        Log::info('diary.summarize.npc_done', [
            'npc_id' => $npc->id,
            'scene_id' => $scene->id,
            'l0_count' => $l0Entries->count(),
            'entry_length' => mb_strlen($entry),
            'finish_reason' => $turn->finishReason,
        ]);

        $vector = $this->embeddings->embed($entry);

        $firstL0 = $l0Entries->first();
        $lastL0 = $l0Entries->last();

        DB::statement(
            'INSERT INTO character_diary_entries
                (character_id, chronicle_id, scene_id, level, from_message_id, to_message_id, entry, embedding, created_at, updated_at)
             VALUES (?, ?, ?, 1, ?, ?, ?, ?::vector, NOW(), NOW())',
            [
                $npc->id,
                $npc->chronicle_id,
                $scene->id,
                $firstL0->from_message_id,
                $lastL0->to_message_id,
                $entry,
                $this->formatVector($vector),
            ],
        );

        return true;
    }

    private function resolveSceneNpcs(Scene $scene): \Illuminate\Support\Collection
    {
        $ids = $scene->participants()
            ->pluck('character_id')
            ->unique()
            ->all();

        if ($ids === []) {
            return collect();
        }

        return Character::query()
            ->whereIn('id', $ids)
            ->where('character_type', CharacterType::Npc)
            ->get();
    }

    /**
     * @param  list<float>  $vector
     */
    private function formatVector(array $vector): string
    {
        return '['.implode(',', array_map(fn (float $v): string => (string) $v, $vector)).']';
    }
}