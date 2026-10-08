<?php

namespace App\Diary;

use App\Enums\CharacterType;
use App\Llm\ChatProvider;
use App\Models\Character;
use App\Models\CharacterDiaryEntry;
use App\Models\Message;
use App\Models\Scene;
use App\Models\WorldEntity;
use App\Models\SceneParticipant;
use App\Prompt\PromptRepository;
use App\Rag\EmbeddingProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class DiaryWriterService
{
    private const MAX_MESSAGES_PER_CALL = 60;

    public function __construct(
        private ChatProvider $chat,
        private EmbeddingProvider $embeddings,
        private PromptRepository $prompts,
    ) {}

    /**
     * @return array{created: int, npcs: int, processed_messages: int, errors: list<string>}
     */
    public function writeForScene(Scene $scene): array
    {
        $scene->loadMissing('gameSession');

        $npcs = $this->resolveSceneNpcs($scene);
        if ($npcs->isEmpty()) {
            return ['created' => 0, 'npcs' => 0, 'processed_messages' => 0, 'errors' => []];
        }

        $created = 0;
        $errors = [];
        $processedMessages = 0;

        foreach ($npcs as $npc) {
            try {
                $result = $this->writeForNpc($npc, $scene);

                if ($result['created']) {
                    $created++;
                    $processedMessages += $result['processed_messages'];
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

        return [
            'created' => $created,
            'npcs' => $npcs->count(),
            'processed_messages' => $processedMessages,
            'errors' => $errors,
        ];
    }

    /**
     * @return array{created: bool, processed_messages: int}
     */
    private function writeForNpc(Character $npc, Scene $scene): array
    {
        $participant = SceneParticipant::query()
            ->where('scene_id', $scene->id)
            ->where('character_id', $npc->id)
            ->where('is_current', true)
            ->first();

        if ($participant === null) {
            return ['created' => false, 'processed_messages' => 0];
        }

        return $this->writeEntryForParticipant($npc, $scene, $participant);
    }

    /**
     * @return array{created: bool, processed_messages: int}
     */
    private function writeEntryForParticipant(
        Character $npc,
        Scene $scene,
        SceneParticipant $participant,
        ?int $upperBoundMessageId = null,
    ): array {
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
            return ['created' => false, 'processed_messages' => 0];
        }

        $formattedMessages = $this->formatMessages($messages);
        $fromMessageId = (int) $messages->first()->id;
        $toMessageId = (int) $messages->last()->id;

        try {
            $entry = $this->generateEntryText(
                $npc,
                $formattedMessages,
                $fromMessageId,
                $toMessageId,
                excludeEntryId: null,
            );
        } catch (\Throwable $e) {
            throw new RuntimeException('LLM call failed: '.$e->getMessage(), 0, $e);
        }

        if ($entry === '') {
            throw new RuntimeException('LLM returned empty diary entry.');
        }

        Log::info('diary.write.npc_done', [
            'npc_id' => $npc->id,
            'scene_id' => $scene->id,
            'from_message_id' => $fromMessageId,
            'to_message_id' => $toMessageId,
            'entry_length' => mb_strlen($entry),
        ]);

        $vector = $this->embeddings->embed($entry);

        DB::statement(
            'INSERT INTO character_diary_entries
                (character_id, chronicle_id, scene_id, level, from_message_id, to_message_id, entry, embedding, created_at, updated_at)
            VALUES (?, ?, ?, 0, ?, ?, ?, ?::vector, NOW(), NOW())',
            [
                $npc->id,
                $npc->chronicle_id,
                $scene->id,
                $fromMessageId,
                $toMessageId,
                $entry,
                $this->formatVector($vector),
            ],
        );

        $participant->update(['last_diary_message_id' => $toMessageId]);

        return ['created' => true, 'processed_messages' => $messages->count()];
    }

    public function writeFinalForNpc(Scene $scene, Character $character): bool
    {
        if ((int) $character->character_type->value !== 'npc') {
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

        return $result['created'];
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
        $newEntry = $this->generateEntryText($character, $formattedMessages, $fromId, $toId, $entry->id);

        if ($newEntry === '') {
            return false;
        }

        $vector = $this->embeddings->embed($newEntry);

        DB::statement(
            'UPDATE character_diary_entries
             SET entry = ?, embedding = ?::vector, updated_at = NOW()
             WHERE id = ?',
            [$newEntry, $this->formatVector($vector), $entry->id],
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
        $clan = $npc->clan?->name ?? '—';
        $sect = $npc->sect?->name ?? '—';

        $traits = $npc->traits
            ->map(fn ($t): string => "- {$t->label}: {$t->value}")
            ->implode("\n");
        if ($traits === '') {
            $traits = '(нет)';
        }

        $bio = $npc->biography?->summary ?? '(нет)';

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
            ['max_tokens' => 2500, 'temperature' => 0.7],
            [],
        );

        return trim($turn->content);
    }

    private function resolveSceneNpcs(Scene $scene): \Illuminate\Support\Collection
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

    private function formatMessages(\Illuminate\Support\Collection $messages): string
    {
        return $messages
            ->map(fn (Message $m): string => $m->displayAuthor().': '.trim((string) $m->body))
            ->implode("\n");
    }

    /**
     * @param  list<float>  $vector
     */
    private function formatVector(array $vector): string
    {
        return '['.implode(',', array_map(fn (float $v): string => (string) $v, $vector)).']';
    }
}