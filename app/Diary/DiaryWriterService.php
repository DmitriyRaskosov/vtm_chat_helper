<?php

namespace App\Diary;

use App\Enums\CharacterType;
use App\Llm\ChatProvider;
use App\Models\Character;
use App\Models\CharacterDiaryEntry;
use App\Models\Message;
use App\Models\Scene;
use App\Models\WorldEntity;
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

        $fromId = (int) ($scene->last_extracted_to_message_id ?? 0);

        $messages = Message::query()
            ->where('scene_id', $scene->id)
            ->where('id', '>', $fromId)
            ->orderBy('id')
            ->limit(self::MAX_MESSAGES_PER_CALL)
            ->get();

        if ($messages->isEmpty()) {
            return ['created' => 0, 'npcs' => 0, 'processed_messages' => 0, 'errors' => []];
        }

        $npcs = $this->resolveSceneNpcs($scene);
        if ($npcs->isEmpty()) {
            return ['created' => 0, 'npcs' => 0, 'processed_messages' => 0, 'errors' => []];
        }

        $formattedMessages = $this->formatMessages($messages);
        $toMessageId = (int) $messages->last()->id;
        $fromMessageId = (int) $messages->first()->id;

        $created = 0;
        $errors = [];

        foreach ($npcs as $npc) {
            try {
                $this->writeForNpc($npc, $scene, $formattedMessages, $fromMessageId, $toMessageId);
                $created++;
            } catch (\Throwable $e) {
                $errors[] = 'NPC '.$npc->id.': '.$e->getMessage();
                Log::error('diary.write.npc_failed', [
                    'npc_id' => $npc->id,
                    'scene_id' => $scene->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($created > 0 || $errors === []) {
            $scene->update(['last_extracted_to_message_id' => $toMessageId]);
        }

        return [
            'created' => $created,
            'npcs' => $npcs->count(),
            'processed_messages' => $messages->count(),
            'errors' => $errors,
        ];
    }

    private function writeForNpc(
        Character $npc,
        Scene $scene,
        string $formattedMessages,
        int $fromMessageId,
        int $toMessageId,
    ): void {
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
            ->orderByDesc('created_at')
            ->value('entry');

        $previousEntryOrNone = is_string($previousEntry) && trim($previousEntry) !== ''
            ? trim($previousEntry)
            : '(нет — это первая запись)';

        try {
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
        } catch (\Throwable $e) {
            throw new RuntimeException('LLM call failed: '.$e->getMessage(), 0, $e);
        }

        $entry = trim($turn->content);
        if ($entry === '') {
            throw new RuntimeException('LLM returned empty diary entry.');
        }

        Log::info('diary.write.npc_done', [
            'npc_id' => $npc->id,
            'scene_id' => $scene->id,
            'entry_length' => mb_strlen($entry),
            'finish_reason' => $turn->finishReason,
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