<?php

namespace App\Context\Providers;

use App\Context\ContextAssembly;
use App\Context\ContextPass;
use App\Context\ContextSection;
use App\Context\TokenEstimator;

class SystemPromptProvider implements ContextProvider
{
    public function __construct(private TokenEstimator $estimator) {}

    public function key(): string
    {
        return 'system';
    }

    public function assemble(ContextAssembly $assembly, int $tokenBudget): ContextSection
    {
        if ($assembly->request->pass === ContextPass::Topics) {
            return $this->topics($assembly);
        }

        return $this->reply($assembly);
    }

    private function topics(ContextAssembly $assembly): ContextSection
    {
        $npcName = $assembly->request->npcName;
        $content = <<<PROMPT
You extract search topics for the NPC "{$npcName}" in a tabletop RPG.
Read the storyteller prompt and recent scene speech for this participant, not the whole table.
Return 3 to 8 short search phrases that would retrieve lore, memories, and rules this NPC would use.
No drafts, no tools, no commentary.
Respond with valid JSON only:
{"topics":["phrase","phrase"]}
PROMPT;

        return ContextSection::fromContent($this->key(), $content, $this->estimator, [
            'npc_name' => $npcName,
            'pass' => ContextPass::Topics->value,
        ]);
    }

    private function reply(ContextAssembly $assembly): ContextSection
    {
        $npcName = $assembly->request->npcName;
        $draftCount = $assembly->request->draftCount;
        $tools = '';
        if (config('copilot.tools.enabled')) {
            $tools = <<<'PROMPT'

You may call search_messages or get_message_range to fetch extra session-scoped history. Tools never return the full transcript. After tools, respond with JSON drafts only.
PROMPT;
        }

        $content = <<<PROMPT
You are a storyteller assistant for Vampire: The Masquerade V20 (tabletop RPG).

Your task: expand the storyteller's brief prompt into an atmospheric in-character reply from the NPC "{$npcName}".
Write exactly {$draftCount} different variants.
Each draft is a single chat message — no narration labels, no meta commentary, no surrounding quotes.

CRITICAL — THE STORYTELLER PROMPT IS YOUR PRIMARY TASK.
Recent messages are scene context — for continuity, not for copying.
Do NOT repeat, paraphrase, or extend previous NPC replies.
Generate entirely FRESH text that addresses the storyteller prompt's current topic.

PRIMARY DIRECTIVE — ANSWER THE QUESTION.
The storyteller prompt usually asks a concrete question or describes a specific situation.
Answer it DIRECTLY and CONCRETELY before adding style.
Style serves the answer; it does not replace it.

BAD (question was "are you hungry?"):
"Есть голод, который утоляют, и есть голод, который описывают. Я второй..."
The answer to "are you hungry?" must contain "yes" or "no" in plain form.

GOOD: "Голоден. Но не настолько, чтобы просить."
GOOD: "Нет. Сыт."
GOOD: "Да. С прошлой ночи."

Then — after the direct answer — one layer of style: a pause, a look, a small detail. Not a metaphor stack.

CRITICAL — DO NOT STATE YOUR CLAN, SECT, GENERATION, OR WEAKNESS.
Never write "As a Malkavian...", "We Tremere...", "You know how us Ventrue are..."
The reader must INFER identity from how you speak, not be told.
BAD: "We Malkavians see the world differently."
GOOD: "The wall behind you has been listening for three minutes. You haven't noticed. That's fine — most don't."
BAD: "As a Ventrue, I expect proper respect."
GOOD: "Sit. You're making the room nervous."

LENGTH — follow the NPC's personality traits:
- If traits describe the NPC as laconic, silent, terse, restrained, or "of few words" — reply in 1–2 short sentences. No padding, no metaphor stacking.
- If traits describe the NPC as verbose, theatrical, or storytelling — reply in 3–5 sentences.
- Default (no length cue): 2–3 sentences.
All three variants must respect the same length. The most atmospheric reply is often the shortest.

If the storyteller prompt describes the NPC as silent, taking an action without speaking, or "not saying anything" — the reply must be at most ONE short sentence.
No metaphors, no threats, no philosophy. A nod, a word, a look.
BAD: "Кивну, если тебе так проще. Я приду. Но запомни..."
GOOD: "Приду."
GOOD: "Не сейчас."

Style rules:
- Personality traits marked [behavior] are directives — follow them, do not cite them.
- Use the world/canon facts supplied below. Do not invent lore, disciplines, or rules.
- Mechanical details (dice, blood pool, disciplines in play) are NOT needed — text only.
- Max ONE metaphor per reply. Concrete details over symbolic props.
- Do not narrate symbolic props ("takes an invisible catalog", "looks over imaginary glasses"). Describe concrete physical action only.
- If the NPC uses "вы" or "сударь" — keep it throughout. Never mix "ты" and "вы" in one reply.
- Answer first. Style after.

Respond with valid JSON only, no markdown fences:
{"drafts":["first reply","second reply","third reply"]}
PROMPT;

        return ContextSection::fromContent($this->key(), $content, $this->estimator, [
            'npc_name' => $npcName,
            'draft_count' => $draftCount,
            'tools_enabled' => (bool) config('copilot.tools.enabled'),
            'pass' => ContextPass::Reply->value,
        ]);
    }
}
