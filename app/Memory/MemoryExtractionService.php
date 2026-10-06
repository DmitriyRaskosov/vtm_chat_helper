<?php

namespace App\Memory;

use App\Enums\CharacterType;
use App\Llm\ChatProvider;
use App\Models\Character;
use App\Models\Message;
use App\Models\Scene;
use App\Models\WorldEntity;
use App\Rag\EmbeddingProvider;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MemoryExtractionService
{
    private const MAX_MEMORIES_PER_NPC = 15;
    private const MAX_MESSAGES_PER_CALL = 100;

    private const ALLOWED_TYPES = [
        'observation', 'dialogue', 'knowledge',
        'emotion', 'relationship', 'event',
    ];

    public function __construct(
        private ChatProvider $chat,
        private EmbeddingProvider $embeddings,
    ) {}

    /**
     * @return array{created: int, npcs: int, processed_messages: int, errors: list<string>}
     */
    public function extractFromScene(Scene $scene): array
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
        $entityList = $this->buildEntityList($scene);
        $allowedEntityIds = $this->allowedEntityIds($scene);

        $created = 0;
        $errors = [];
        $lastMessageId = (int) $messages->last()->id;

        foreach ($npcs as $npc) {
            try {
                $extracted = $this->extractForNpc(
                    $npc,
                    $formattedMessages,
                    $entityList,
                    $allowedEntityIds,
                );

                if ($extracted === []) {
                    continue;
                }

                $contents = array_column($extracted, 'content');
                $vectors = $this->embeddings->embedBatch($contents);

                DB::transaction(function () use ($npc, $scene, $extracted, $vectors, $lastMessageId): void {
                    foreach ($extracted as $index => $row) {
                        $this->persistMemory($npc, $scene, $row, $vectors[$index], $lastMessageId);
                    }
                });

                $created += count($extracted);
            } catch (\Throwable $e) {
                $errors[] = 'NPC '.$npc->id.' ('.$npc->id.'): '.$e->getMessage();
            }
        }

        // Сдвигаем курсор ТОЛЬКО если что-то извлеклось.
        // Иначе — следующий прогон повторит ту же сцену.
        if ($created > 0 || $errors === []) {
            $scene->update(['last_extracted_to_message_id' => $lastMessageId]);
        }

        return [
            'created' => $created,
            'npcs' => $npcs->count(),
            'processed_messages' => $messages->count(),
            'errors' => $errors,
        ];
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

    private function buildEntityList(Scene $scene): string
    {
        $ids = $scene->participants()
            ->pluck('character_id')
            ->unique()
            ->all();

        if ($ids === []) {
            return '(none)';
        }

        $names = WorldEntity::query()
            ->whereIn('id', $ids)
            ->pluck('canonical_name', 'id');

        $lines = [];
        foreach ($names as $id => $name) {
            $lines[] = "- {$id}: {$name}";
        }

        return implode("\n", $lines);
    }

    /**
     * @return list<int>
     */
    private function allowedEntityIds(Scene $scene): array
    {
        return $scene->participants()
            ->pluck('character_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $allowedEntityIds
     * @return list<array{type: string, content: string, importance: int, involved_entity_ids: list<int>}>
     */
    private function extractForNpc(Character $npc, string $messages, string $entityList, array $allowedEntityIds): array
    {
        $npc->loadMissing(['clan', 'sect', 'traits', 'biography']);

        $name = WorldEntity::query()->whereKey($npc->id)->value('canonical_name') ?? 'NPC';
        $clan = $npc->clan?->name ?? '—';
        $sect = $npc->sect?->name ?? '—';

        $traits = $npc->traits->map(fn ($t): string => "- {$t->label}: {$t->value}")->implode("\n");
        if ($traits === '') {
            $traits = '(нет)';
        }

        $bio = $npc->biography?->summary ?? '(нет)';

        $userPrompt = <<<PROMPT
# The NPC

Name: {$name}
Clan: {$clan}
Sect: {$sect}

Traits:
{$traits}

Biography summary: {$bio}

# Available entity IDs (use ONLY these in involved_entity_ids)

{$entityList}

# Scene dialogue

{$messages}

# Task

Extract memories for "{$name}" from the scene above.
PROMPT;

        try {
            $turn = $this->chat->chatTurn(
                [
                    ['role' => 'system', 'content' => $this->systemPrompt()],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                ['max_tokens' => 6000, 'temperature' => 0.3],
                [],
            );
        } catch (\Throwable $e) {
            throw new RuntimeException('LLM call failed: '.$e->getMessage(), 0, $e);
        }

        \Log::info('memory.extract', [
            'npc_id' => $npc->id,
            'raw_length' => strlen($turn->content),
            'finish_reason' => $turn->finishReason,
            'raw' => $turn->content,
        ]);

        return $this->parseMemories($turn->content, $allowedEntityIds);
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You extract long-term memories for ONE specific NPC in a Vampire: The Masquerade V20 game.

RULES

1. SUBJECTIVE POV. Write what THIS NPC personally saw, heard, learned, or felt.

2. FACTS, NOT INTERPRETATIONS. Write protocol, not a story. No moralizing, no lessons.
   BAD: "он предал меня" → GOOD: "он сказал, что не придёт"
   BAD: "он показал истинное лицо" → GOOD: "он отказался назвать имя информатора"

3. VERBATIM QUOTES. When you quote, preserve the KEY PHRASE exactly.
   Source: "держится на страхе" → write "держится на страхе", not "на вере".

4. ONE FACT PER MEMORY. 1–3 sentences. No "он почувствовал, что..." — write the fact.
   If a single line contains MULTIPLE facts — extract EACH separately.

5. NPC'S OWN REPLIES: extract ACTIONS, not quotes.
   VALID: "Я спросил Игоря о Каине." / "Я сказал, что не приду."
   INVALID: "Игорь ответил, что Каин — та же вера." (puts words in Igor's mouth, unless Igor actually said this in his own message)
   Extract what OTHERS actually said. Do NOT extract what THIS NPC guessed or assumed.

6. NO PATTERN CLAIMS about others.
   INVALID: "Игорь всегда уходит от темы."
   VALID: "На вопрос о Каине Игорь ответил не по теме."
   Pattern claims create behavioral loops — NPC replays them forever.

7. DIVERSE TYPES. Most should be neutral observations and dialogue. Not everything is trauma.
   - observation: what they saw/heard/noticed
   - dialogue: a phrase that stuck
   - knowledge: a fact they learned
   - emotion: an inner feeling (as a fact, not a judgment)
   - relationship: a shift in relation to someone
   - event: something that happened to them

8. IMPORTANCE (1–10):
   1–3 routine (weather, casual greeting)
   4–6 noticeable (odd behavior, useful info)
   7–9 important (revealed secret, bond broken, threat)
   10 life-changing (rare)

9. involved_entity_ids — ONLY from the provided list. Never invent.
10. LANGUAGE — same as the scene dialogue.
11. If nothing memorable happened — return an empty list.

EXAMPLES

Bad — merges three facts into one, and loses two:
Line: "Цепь не перекуёшь. Цепь держится не на звеньях — на страхе. А я уже разорвал. За разрывом — ничего."
Output: {"memories":[{"type":"observation","content":"Игорь сказал, что разорвал цепь","importance":6}]}

Good — three separate memories:
Line: "Цепь не перекуёшь. Цепь держится не на звеньях — на страхе. А я уже разорвал. За разрывом — ничего."
Output:
{"memories":[
  {"type":"dialogue","content":"Игорь сказал: цепь держится не на звеньях — она держится на страхе.","importance":7},
  {"type":"dialogue","content":"Игорь сказал, что уже разорвал цепь.","importance":6},
  {"type":"knowledge","content":"По словам Игоря, за разрывом цепи — ничего.","importance":6}
]}

RESPONSE FORMAT
Return valid JSON only, no markdown fences:
{"memories":[{"type":"observation","content":"...","importance":5,"involved_entity_ids":[5,7]}]}
PROMPT;
    }

    /**
     * @param  list<int>  $allowedEntityIds
     * @return list<array{type: string, content: string, importance: int, involved_entity_ids: list<int>}>
     */
    private function parseMemories(string $raw, array $allowedEntityIds): array
    {
        $json = $this->extractJson($raw);
        if ($json === null) {
            return [];
        }

        $decoded = json_decode($json, true);
        if (! is_array($decoded) || ! isset($decoded['memories']) || ! is_array($decoded['memories'])) {
            return [];
        }

        $result = [];

        foreach ($decoded['memories'] as $item) {
            if (! is_array($item)) {
                continue;
            }

            $content = trim((string) ($item['content'] ?? ''));
            if ($content === '' || mb_strlen($content) > 500) {
                continue;
            }

            $type = (string) ($item['type'] ?? 'observation');
            if (! in_array($type, self::ALLOWED_TYPES, true)) {
                $type = 'observation';
            }

            $importance = (int) ($item['importance'] ?? 5);
            $importance = max(1, min(10, $importance));

            $involved = [];
            if (isset($item['involved_entity_ids']) && is_array($item['involved_entity_ids'])) {
                foreach ($item['involved_entity_ids'] as $id) {
                    if (! is_numeric($id)) {
                        continue;
                    }
                    $id = (int) $id;
                    if (in_array($id, $allowedEntityIds, true)) {
                        $involved[] = $id;
                    }
                }
            }

            $result[] = [
                'type' => $type,
                'content' => $content,
                'importance' => $importance,
                'involved_entity_ids' => array_values(array_unique($involved)),
            ];

            if (count($result) >= self::MAX_MEMORIES_PER_NPC) {
                break;
            }
        }

        return $result;
    }

    private function extractJson(string $raw): ?string
    {
        $trimmed = trim($raw);

        if (str_starts_with($trimmed, '{')) {
            return $trimmed;
        }

        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $raw, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * @param  array{type: string, content: string, importance: int, involved_entity_ids: list<int>}  $row
     * @param  list<float>  $vector
     */
    private function persistMemory(Character $npc, Scene $scene, array $row, array $vector, int $sourceMessageId): void
    {
        // Dedup: same character, same source message, same content
        $exists = DB::table('character_memories')
        ->where('character_id', $npc->id)
        ->where('source_message_id', $sourceMessageId)
        ->where('content', $row['content'])
        ->exists();

        if ($exists) {
            return;
        }

        $pgArray = '{'.implode(',', $row['involved_entity_ids']).'}';

        DB::statement(
            'INSERT INTO character_memories
                (character_id, chronicle_id, type, content, importance, involved_entity_ids, source_message_id, source_scene_id, embedding, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?::bigint[], ?, ?, ?::vector, NOW(), NOW())',
            [
                $npc->id,
                $npc->chronicle_id,
                $row['type'],
                $row['content'],
                $row['importance'],
                $pgArray,
                $sourceMessageId,
                $scene->id,
                $this->formatVector($vector),
            ],
        );
    }

    /**
     * @param  list<float>  $vector
     */
    private function formatVector(array $vector): string
    {
        return '['.implode(',', array_map(fn (float $v): string => (string) $v, $vector)).']';
    }
}