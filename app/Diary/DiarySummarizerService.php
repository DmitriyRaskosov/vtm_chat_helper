<?php

namespace App\Diary;

use App\Enums\CharacterType;
use App\Llm\ChatProvider;
use App\Models\Character;
use App\Models\CharacterDiaryEntry;
use App\Models\Scene;
use App\Models\WorldEntity;
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

        if ($l0Entries->count() < 3) {
            Log::info('diary.summarize.too_few_entries', [
                'npc_id' => $npc->id,
                'scene_id' => $scene->id,
                'l0_count' => $l0Entries->count(),
            ]);

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

        $userPrompt = <<<PROMPT
# NPC

Name: {$name}
Clan: {$clan}
Sect: {$sect}

Traits:
{$traits}

# Short diary entries from this night

{$entriesText}

# Task

Write the final diary entry for this night for {$name}.
PROMPT;

        try {
            $turn = $this->chat->chatTurn(
                [
                    ['role' => 'system', 'content' => $this->systemPrompt()],
                    ['role' => 'user', 'content' => $userPrompt],
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

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You are summarizing a full night in the diary of ONE specific NPC
in a Vampire: The Masquerade V20 game.

The NPC wrote several short diary entries during the night.
Now they are writing ONE final, longer entry that looks back at the whole night.

CRITICAL — VOICE.
Preserve the same voice, tone, rhythm, and vocabulary as the short entries.
The final entry is still the same person writing — same style, same obsessions.

EXAMPLE OF CORRECT COMPRESSION

Short entries (150 words):
"Я боюсь огня. Не их клыков, а того, что они приносят — спичку, окурок.
Одна искра, и полка, которую я собирал двести лет, — пепел. А пепел
не читается. Я не ненавижу их. Ненависть — это когда хочешь сжечь
чужой том. Я хочу лишь, чтобы не сожгли мой."

Summary (25 words):
"Ночь оставила одно: я боюсь огня, который они приносят. Не их самих —
того, что они могут сжечь то, что я собирал двести лет."

TASK
Read the short entries and write a single, cohesive final entry.

RULES

1. This is a CONDENSED SUMMARY, not a re-telling. It must be at least
   3× SHORTER than the sum of the short entries.
2. LENGTH — HARD LIMIT 150 words. Count them. If you're over — cut, don't trim.
3. ABSOLUTELY FORBIDDEN:
   - Copying any phrase, metaphor, or image verbatim from the short entries.
   - Using the same signature expressions ("ведомость трещин", "шёпот полок",
     "издание в мягкой обложке", "опись", "переплёт", "оглавление",
     "закладка", "том", "индекс реальности") MORE THAN ONCE.
   - Restating what was already said in the short entries with different words.
4. What to KEEP (only these):
   - ONE sentence: what happened (the key event).
   - ONE sentence: the strongest FEELING of the night.
   - ONE sentence: what CHANGED in your thinking.
   - ONE sentence: current state of relationships.
5. DROP:
   - All metaphor stacks.
   - All self-commentary about writing ("я записал", "я занёс").
   - All specific details that don't fit the four sentences above.
6. Tone is free. Same language as the short entries. First person, past tense.

EXAMPLE (from a different character)

Short entries (3 entries, 500 words total) contain:
- The NPC met a rival at a bar.
- They argued about honor.
- The rival insulted the NPC's family.
- The NPC felt cold rage.
- The NPC decided not to fight — for now.
- The NPC now distrusts the rival more.

CORRECT summary (28 words):
"Я встретил врага в баре. Мы говорили о чести — и он задел мою семью.
Я не стал драться. Сейчас — нет. Но я запомнил."

WRONG summary (too long, duplicates):
"Я встретил врага в баре, и мы говорили о чести. Он сказал про мою
семью — я почувствовал холодную ярость. Я не стал драться, но я
запомнил. Теперь я доверяю ему меньше."

CRITICAL — DO NOT INVENT.
If unsure whether a detail was in the short entries — leave it out.
Less is more. A short, faithful summary beats a long, invented story.

ANTI-ANCHOR
Do not repeat the same descriptive detail in every entry / reply.
If you mentioned "пальцы в чернилах" last time — do not mention it now.
Vary imagery, gestures, physical actions. A character is more than one trait.

TRAIT EXAMPLES
If a trait contains specific example phrases (e.g. catalog codes,
stock phrases), treat them as PATTERN EXAMPLES, not required quotes.
Use them to infer the FORMAT, then produce YOUR OWN variations.
Do NOT repeat the same example phrase twice across entries.

RESPONSE FORMAT — return ONLY the final diary text.
No JSON. No quotes around the whole entry. No preamble. No "Дневник:".
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