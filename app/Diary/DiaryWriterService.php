<?php

namespace App\Diary;

use App\Diary\Data\WriteEntryResult;
use App\Diary\Data\WriteSceneResult;
use App\Diary\Exceptions\DiaryGenerationFailedException;
use App\Enums\CharacterType;
use App\Http\Controllers\ChatController;
use App\Jobs\WriteDiaryJob;
use App\Llm\ChatProvider;
use App\Models\Character;
use App\Models\CharacterDiaryEntry;
use App\Models\Message;
use App\Models\Scene;
use App\Models\SceneParticipant;
use App\Models\WorldEntity;
use App\Prompt\PromptRepository;
use App\Rag\EmbeddingProvider;
use App\Rag\VectorLiteral;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class DiaryWriterService
{
    private const MAX_MESSAGES_PER_CALL = 60;

    public function __construct(
        private ChatProvider $chat,
        private EmbeddingProvider $embeddings,
        private PromptRepository $prompts,
        private DiaryEntryRepository $entries,
    ) {}

    /**
     * Пишет все сообщения сцены после курсора дневника каждого NPC-участника (до
     * {@see self::MAX_MESSAGES_PER_CALL} на NPC). `write_threshold` из config не читает —
     * порог только в {@see ChatController::maybeDispatchDiaryWrite} перед {@see WriteDiaryJob}.
     */
    public function writeForScene(Scene $scene): WriteSceneResult
    {
        $scene->loadMissing('gameSession');

        $npcs = $this->resolveSceneNpcs($scene);
        if ($npcs->isEmpty()) {
            return new WriteSceneResult(0, 0, 0, []);
        }

        $created = 0;
        $errors = [];
        $processedMessages = 0;

        foreach ($npcs as $npc) {
            try {
                $result = $this->writeForNpc($npc, $scene);

                if ($result->created) {
                    $created++;
                    $processedMessages += $result->processedMessages;
                }
            } catch (\Throwable $e) {
                $errors[] = 'NPC '.$npc->id.': '.$e->getMessage();
                Log::error('diary.write.npc_failed', [
                    'npc_id' => $npc->id,
                    'scene_id' => $scene->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return new WriteSceneResult(
            $created,
            $npcs->count(),
            $processedMessages,
            $errors,
        );
    }

    private function writeForNpc(Character $npc, Scene $scene): WriteEntryResult
    {
        $participant = SceneParticipant::query()
            ->where('scene_id', $scene->id)
            ->where('character_id', $npc->id)
            ->where('is_current', true)
            ->first();

        if ($participant === null) {
            return new WriteEntryResult(false, 0);
        }

        return $this->writeEntryForParticipant($npc, $scene, $participant);
    }

    private function writeEntryForParticipant(
        Character $npc,
        Scene $scene,
        SceneParticipant $participant,
        ?int $upperBoundMessageId = null,
    ): WriteEntryResult {
        $fromId = (int) ($participant->last_diary_message_id ?? 0);
        $minId = (int) ($participant->entered_message_id ?? 0);

        $query = Message::query()
            ->where('scene_id', $scene->id)
            ->where('is_ooc', false)
            ->where('id', '>', $fromId)
            ->where('id', '>=', $minId)
            ->orderBy('id')
            ->limit(self::MAX_MESSAGES_PER_CALL);

        if ($upperBoundMessageId !== null) {
            $query->where('id', '<=', $upperBoundMessageId);
        }

        $messages = $query->get();

        if ($messages->isEmpty()) {
            return new WriteEntryResult(false, 0);
        }

        $toMessageId = (int) $messages->last()->id;
        $roleplayMessages = $messages->where('is_system', false)->values();

        if ($roleplayMessages->isEmpty()) {
            $participant->update(['last_diary_message_id' => $toMessageId]);

            return new WriteEntryResult(false, 0);
        }

        $formattedMessages = $this->formatMessages($messages);
        $fromMessageId = (int) $roleplayMessages->first()->id;

        try {
            $raw = $this->generateEntryText(
                $npc,
                $formattedMessages,
                $fromMessageId,
                $toMessageId,
                excludeEntryId: null,
            );
        } catch (\Throwable $e) {
            throw new DiaryGenerationFailedException('LLM call failed: '.$e->getMessage(), $e);
        }

        if ($raw === '') {
            throw new DiaryGenerationFailedException('LLM returned empty diary entry.');
        }

        $parsed = DiaryMetadataParser::parse($raw);
        $entry = $parsed['entry'];
        if ($entry === '') {
            throw new DiaryGenerationFailedException('LLM returned empty diary entry after parsing.');
        }

        Log::info('diary.write.npc_done', [
            'npc_id' => $npc->id,
            'scene_id' => $scene->id,
            'from_message_id' => $fromMessageId,
            'to_message_id' => $toMessageId,
            'entry_length' => mb_strlen($entry),
            'importance' => $parsed['importance'],
            'emotion' => $parsed['emotion']?->value,
        ]);

        $vector = $this->embeddings->embed($entry);

        $this->entries->insertL0(
            $npc,
            $scene,
            $participant,
            $fromMessageId,
            $toMessageId,
            $entry,
            $parsed['importance'],
            $parsed['emotion']?->value,
            VectorLiteral::fromArray($vector),
        );

        return new WriteEntryResult(true, $messages->count());
    }

    /**
     * Финальный срез L0, когда NPC уходит из сцены (до `left_message_id`).
     * Игровые персонажи ({@link CharacterType::Player}) пропускаются: дневник PC вне продукта.
     */
    public function writeFinalForNpc(Scene $scene, Character $character): bool
    {
        if (! $character->isNpc()) {
            return false;
        }

        $participant = SceneParticipant::query()
            ->where('scene_id', $scene->id)
            ->where('character_id', $character->id)
            ->whereNotNull('left_message_id')
            ->orderByDesc('id')
            ->first();

        if ($participant === null) {
            return false;
        }

        $result = $this->writeEntryForParticipant(
            $character,
            $scene,
            $participant,
            upperBoundMessageId: (int) $participant->left_message_id,
        );

        return $result->created;
    }

    public function regenerateEntry(CharacterDiaryEntry $entry): bool
    {
        if ((int) $entry->level !== 0) {
            return false;
        }

        $character = Character::query()->find($entry->character_id);
        $scene = $entry->scene_id !== null ? Scene::query()->find($entry->scene_id) : null;

        if ($character === null || $scene === null) {
            return false;
        }

        $fromId = (int) ($entry->from_message_id ?? 0);
        $toId = (int) ($entry->to_message_id ?? 0);

        if ($fromId === 0 || $toId === 0) {
            return false;
        }

        $messages = Message::query()
            ->where('scene_id', $scene->id)
            ->where('is_ooc', false)
            ->where('id', '>=', $fromId)
            ->where('id', '<=', $toId)
            ->orderBy('id')
            ->get();

        if ($messages->isEmpty()) {
            return false;
        }

        $formattedMessages = $this->formatMessages($messages);
        if ($formattedMessages === '') {
            return false;
        }

        $raw = $this->generateEntryText($character, $formattedMessages, $fromId, $toId, $entry->id);

        if ($raw === '') {
            return false;
        }

        $parsed = DiaryMetadataParser::parse($raw);
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
        );

        Log::info('diary.regenerate.entry_done', [
            'entry_id' => $entry->id,
            'npc_id' => $entry->character_id,
        ]);

        return true;
    }

    private function generateEntryText(
        Character $npc,
        string $formattedMessages,
        int $fromMessageId,
        int $toMessageId,
        ?int $excludeEntryId = null,
    ): string {
        $npc->loadMissing(['clan', 'sect', 'traits', 'biography']);

        $name = WorldEntity::query()->whereKey($npc->id)->value('canonical_name') ?? 'NPC';
        $clan = $npc->clan !== null ? $npc->clan->name : '—';
        $sect = $npc->sect !== null ? $npc->sect->name : '—';

        $traits = $npc->traits
            ->map(fn ($t): string => "- {$t->label}: {$t->value}")
            ->implode("\n");
        if ($traits === '') {
            $traits = '(нет)';
        }

        $bio = $npc->biography !== null ? ($npc->biography->summary ?? '(нет)') : '(нет)';

        $previousEntry = CharacterDiaryEntry::query()
            ->where('character_id', $npc->id)
            ->when($excludeEntryId !== null, fn ($q) => $q->where('id', '!=', $excludeEntryId))
            ->where('created_at', '<', now())
            ->orderByDesc('created_at')
            ->value('entry');

        $previousEntryOrNone = is_string($previousEntry) && trim($previousEntry) !== ''
            ? trim($previousEntry)
            : '(нет — это первая запись)';

        $turn = $this->chat->chatTurn(
            [
                ['role' => 'system', 'content' => $this->prompts->get('diary.writer.system')],
                ['role' => 'user', 'content' => $this->prompts->get('diary.writer.user', [
                    'name' => $name,
                    'clan' => $clan,
                    'sect' => $sect,
                    'traits' => $traits,
                    'bio' => $bio,
                    'previousEntryOrNone' => $previousEntryOrNone,
                    'fromMessageId' => $fromMessageId,
                    'toMessageId' => $toMessageId,
                    'formattedMessages' => $formattedMessages,
                ])],
            ],
            [
                'max_tokens' => (int) config('diary.write.max_output_tokens'),
                'temperature' => (float) config('diary.write.temperature'),
            ],
        );

        return trim($turn->content);
    }

    private function resolveSceneNpcs(Scene $scene): Collection
    {
        $ids = $scene->participants()
            ->where('is_current', true)
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

    private function formatMessages(Collection $messages): string
    {
        return $messages
            ->filter(fn (Message $m): bool => ! $m->is_system)
            ->map(fn (Message $m): string => $m->displayAuthor().': '.trim((string) $m->body))
            ->implode("\n");
    }
}
