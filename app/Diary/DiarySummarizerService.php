<?php

namespace App\Diary;

use App\Diary\Data\DiaryLlmCallResult;
use App\Diary\Data\SummarizeSceneResult;
use App\Diary\Exceptions\DiaryGenerationFailedException;
use App\Enums\CharacterType;
use App\Llm\ChatProvider;
use App\Models\Character;
use App\Models\CharacterDiaryEntry;
use App\Models\Scene;
use App\Models\WorldEntity;
use App\Prompt\PromptRepository;
use App\Rag\EmbeddingProvider;
use App\Rag\VectorLiteral;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class DiarySummarizerService
{
    use RetriesLlmOutput;

    private const MAX_L0_ENTRIES = 30;

    public function __construct(
        private ChatProvider $chat,
        private EmbeddingProvider $embeddings,
        private PromptRepository $prompts,
        private DiaryEntryRepository $entries,
    ) {}

    public function summarizeScene(
        Scene $scene,
        ?int $combatEncounterId = null,
        ?int $characterId = null,
    ): SummarizeSceneResult {
        $npcs = $this->resolveSceneNpcs($scene, $characterId);
        if ($npcs->isEmpty()) {
            return new SummarizeSceneResult(0, 0, 0, []);
        }

        $created = 0;
        $skipped = 0;
        $errors = [];

        foreach ($npcs as $npc) {
            try {
                $result = $this->summarizeForNpc($npc, $scene, $combatEncounterId);

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

        return new SummarizeSceneResult(
            $created,
            $npcs->count(),
            $skipped,
            $errors,
        );
    }

    private function summarizeForNpc(Character $npc, Scene $scene, ?int $combatEncounterId = null): bool
    {
        $lastToMessageId = (int) (CharacterDiaryEntry::query()
            ->where('character_id', $npc->id)
            ->where('scene_id', $scene->id)
            ->where('level', 1)
            ->max('to_message_id') ?? 0);

        $l0Query = CharacterDiaryEntry::query()
            ->where('character_id', $npc->id)
            ->where('scene_id', $scene->id)
            ->where('level', 0)
            ->where('is_stale', false)
            ->where('from_message_id', '>', $lastToMessageId)
            ->orderBy('created_at')
            ->limit(self::MAX_L0_ENTRIES);

        if ($combatEncounterId !== null) {
            $l0Query->where('combat_encounter_id', $combatEncounterId);
        } else {
            $l0Query->whereNull('combat_encounter_id');
        }

        $l0Entries = $l0Query->get();

        if ($l0Entries->count() < 2) {
            if ($l0Entries->count() === 1) {
                Log::info('diary.summarize.skipped_single_l0', [
                    'npc_id' => $npc->id,
                    'scene_id' => $scene->id,
                    'combat_encounter_id' => $combatEncounterId,
                ]);
            }

            return false;
        }

        try {
            $llm = $this->generateSummaryText($npc, $scene, $l0Entries);
        } catch (\Throwable $e) {
            throw new DiaryGenerationFailedException('LLM call failed: '.$e->getMessage(), $e);
        }

        if ($llm->content === '') {
            throw new DiaryGenerationFailedException('LLM returned empty L1 entry.');
        }

        $parsed = DiaryMetadataParser::parse($llm->content);
        $entry = $parsed['entry'];
        if ($entry === '') {
            throw new DiaryGenerationFailedException('LLM returned empty L1 entry after parsing.');
        }

        Log::info('diary.summarize.npc_done', [
            'npc_id' => $npc->id,
            'scene_id' => $scene->id,
            'l0_count' => $l0Entries->count(),
            'entry_length' => mb_strlen($entry),
            'importance' => $parsed['importance'],
            'emotion' => $parsed['emotion']?->value,
        ]);

        $vector = $this->embeddings->embed($entry);

        $firstL0 = $l0Entries->first();
        $lastL0 = $l0Entries->last();

        $this->entries->insertL1(
            $npc,
            $scene,
            (int) $firstL0->from_message_id,
            (int) $lastL0->to_message_id,
            $entry,
            $parsed['importance'],
            $parsed['emotion']?->value,
            VectorLiteral::fromArray($vector),
            $this->chronicleInGameDate($scene),
            $llm->isTruncated,
            $combatEncounterId,
        );

        return true;
    }

    /**
     * @param  Collection<int, CharacterDiaryEntry>  $l0Entries
     */
    private function generateSummaryText(Character $npc, Scene $scene, Collection $l0Entries): DiaryLlmCallResult
    {
        $npc->loadMissing(['clan', 'sect']);

        $name = WorldEntity::query()->whereKey($npc->id)->value('canonical_name') ?? 'NPC';
        $clan = $npc->clan !== null ? $npc->clan->name : '—';
        $sect = $npc->sect !== null ? $npc->sect->name : '—';

        $traits = collect($npc->sheetTraits())
            ->map(fn (array $trait): string => '- '.(string) ($trait['label'] ?? '').': '.(string) ($trait['value'] ?? ''))
            ->implode("\n");
        if ($traits === '') {
            $traits = '(нет)';
        }

        $entriesText = $l0Entries
            ->map(fn (CharacterDiaryEntry $e, int $i): string => '[entry '.($i + 1)."]\n".trim($e->entry))
            ->implode("\n\n");

        return $this->callWithRetry(
            $this->chat,
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
            [
                'max_tokens' => (int) config('diary.summarize.max_output_tokens'),
                'temperature' => (float) config('diary.summarize.temperature'),
            ],
            (int) $npc->id,
            (int) $scene->id,
        );
    }

    private function chronicleInGameDate(Scene $scene): ?\DateTimeInterface
    {
        $scene->loadMissing('gameSession.chronicle');

        return $scene->gameSession?->chronicle?->current_in_game_date;
    }

    private function resolveSceneNpcs(Scene $scene, ?int $characterId = null): Collection
    {
        if ($characterId !== null) {
            $character = Character::query()
                ->whereKey($characterId)
                ->where('character_type', CharacterType::Npc)
                ->first();

            return $character === null ? collect() : collect([$character]);
        }

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

    public function regenerateSummary(CharacterDiaryEntry $entry): bool
    {
        if ((int) $entry->level !== 1) {
            return false;
        }

        $character = Character::query()->find($entry->character_id);
        $scene = $entry->scene_id !== null ? Scene::query()->find($entry->scene_id) : null;

        if ($character === null || $scene === null) {
            return false;
        }

        $l0Entries = CharacterDiaryEntry::query()
            ->where('character_id', $character->id)
            ->where('scene_id', $scene->id)
            ->where('level', 0)
            ->orderBy('created_at')
            ->limit(self::MAX_L0_ENTRIES)
            ->get();

        if ($l0Entries->isEmpty()) {
            return false;
        }

        $llm = $this->generateSummaryText($character, $scene, $l0Entries);

        if ($llm->content === '') {
            return false;
        }

        $parsed = DiaryMetadataParser::parse($llm->content);
        $newEntry = $parsed['entry'];
        if ($newEntry === '') {
            return false;
        }

        $vector = $this->embeddings->embed($newEntry);

        $this->entries->updateContent(
            (int) $entry->id,
            $newEntry,
            $parsed['importance'],
            $parsed['emotion']?->value,
            VectorLiteral::fromArray($vector),
            $llm->isTruncated,
        );

        Log::info('diary.regenerate.summary_done', [
            'entry_id' => $entry->id,
            'npc_id' => $entry->character_id,
        ]);

        return true;
    }
}
