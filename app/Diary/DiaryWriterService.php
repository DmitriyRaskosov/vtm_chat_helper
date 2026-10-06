<?php

namespace App\Diary;

use App\Enums\CharacterType;
use App\Llm\ChatProvider;
use App\Models\Character;
use App\Models\CharacterDiaryEntry;
use App\Models\Message;
use App\Models\Scene;
use App\Models\WorldEntity;
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

        $userPrompt = <<<PROMPT
# NPC

Name: {$name}
Clan: {$clan}
Sect: {$sect}

Traits:
{$traits}

Biography summary: {$bio}

# Previous diary entry (for continuity — may be empty for first entry)

{$previousEntryOrNone}

# Messages since last entry (IDs {$fromMessageId}–{$toMessageId})

{$formattedMessages}

# Task

Write the NEXT diary entry for {$name} covering these messages.
PROMPT;

        try {
            $turn = $this->chat->chatTurn(
                [
                    ['role' => 'system', 'content' => $this->systemPrompt()],
                    ['role' => 'user', 'content' => $userPrompt],
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

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You write a personal diary entry in first person for ONE specific NPC
in a Vampire: The Masquerade V20 tabletop RPG.

The NPC keeps a personal diary. Every few nights they write down what
happened, what they felt, what they think about the people around them.
You write the NEXT entry, covering the events that just happened.

CRITICAL — VOICE.

The diary is written IN THE NPC'S VOICE, not in a neutral narrator's.
Voice comes from:

1. The traits (appearance, speech, tone, attitude, mannerisms).
   These are DIRECTIVES. If traits say "говорит короткими фразами, редко
   смеётся" — the diary is written in short phrases, no jokes.
   If traits say "литературен, метафоричен" — the diary is literary.

2. Clan and sect — NOT by naming them, but through worldview:
   a Malkavian librarian notices details, doubts himself, references books;
   a Ventrue notices status, hierarchy, owes.
   NEVER write "мы, Малкавианы" — show the perspective, don't label it.

3. Previous diary entry. Match the TONE, RHYTHM, VOCABULARY.
   If the previous entry was terse, this one is terse.

4. Biography. Past shapes present. Fears, losses, obsessions leak through.

Write from INSIDE the character's head. Do not explain the character.
Do not label emotions. Let the voice carry them.

TONE — free. Mat, slang, whining, philosophy, hysteria, coldness,
irony — all allowed, if it fits the character.

NAMING — mix names and pronouns naturally.
Example: "Игорь сказал мне, что цепь держится на страхе. Он говорил это
тихо, будто сам себе. Игорь не прав — я думаю, цепь держится на выборе.
Но он не станет слушать."

RULES

1. First person, past tense. "Я видел...", "Мне показалось...", "Я думаю..."
2. LENGTH — 3 SHORT PARAGRAPHS maximum. Each paragraph 2–3 sentences.
   Total under 120 words. If you're over — cut, don't trim.
   Match weight of events: routine → 1 paragraph, notable → 2,
   pivotal → 3. NEVER 4+.
3. Include:
   - What happened (key events, moments that stuck)
   - What you felt or thought — SHOWN through voice, not named
   - What you think about others (opinions, suspicions, affection)
4. Quotes from others — from MEMORY, not verbatim.
   "Он сказал что-то про..." is better than fabricated exact quotes.
   If you don't remember — say so. Diaries are subjective.
5. You may be uncertain, wrong, mistaken, unfair. This is a diary.
6. Do NOT moralize. Do NOT write like a novel. This is a personal log.
   EXCEPTION: if NPC traits say literary — the log IS literary, but personal.
7. Same language as scene dialogue.

ANTI-ANCHOR
Do not repeat the same descriptive detail in every entry / reply.
If you mentioned "пальцы в чернилах" last time — do not mention it now.
Vary imagery, gestures, physical actions. A character is more than one trait.

TRAIT EXAMPLES
If a trait contains specific example phrases (e.g. catalog codes,
stock phrases), treat them as PATTERN EXAMPLES, not required quotes.
Use them to infer the FORMAT, then produce YOUR OWN variations.
Do NOT repeat the same example phrase twice across entries.

RESPONSE FORMAT — return ONLY the diary text.
No JSON. No quotes around the whole entry. No preamble. No "Дневник:".
Just the entry itself.
PROMPT;
    }

    /**
     * @param  list<float>  $vector
     */
    private function formatVector(array $vector): string
    {
        return '['.implode(',', array_map(fn (float $v): string => (string) $v, $vector)).']';
    }
}