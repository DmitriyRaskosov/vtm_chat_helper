<?php

namespace App\Context\Providers;

use App\Context\ContextAssembly;
use App\Context\ContextSection;
use App\Context\LineTrimmer;
use App\Context\TokenEstimator;
use App\Prompt\PromptRepository;

class NpcIdentityProvider implements ContextProvider
{
    public function __construct(
        private TokenEstimator $estimator,
        private LineTrimmer $trimmer,
        private PromptRepository $prompts,
    ) {}

    public function key(): string
    {
        return 'npc_identity';
    }

    public function assemble(ContextAssembly $assembly, int $tokenBudget): ContextSection
    {
        $npcName = $assembly->request->npcName;
        $disciplineIds = [];
        $entityId = $assembly->entity?->id;
        $character = $assembly->character;

        $clanLabel = 'unknown';
        $sectLabel = 'unknown';
        if ($character !== null) {
            $character->loadMissing(['clan', 'sect', 'disciplines.discipline']);
            $clanLabel = $this->canonName($character->clan?->name);
            $sectLabel = $this->canonName($character->sect?->name);
        }

        $lines = [
            "You are {$npcName}, clan {$clanLabel}, sect {$sectLabel}. This is who you are. Do not claim other clans or sects as yours when they are mentioned in conversation.",
            "NPC: {$npcName}",
        ];

        if ($character !== null) {
            $canonical = $assembly->entity?->canonical_name;
            if (is_string($canonical) && $canonical !== '' && $canonical !== $npcName) {
                $lines[] = "Canonical name: {$canonical}";
            }

            $lines[] = 'Type: '.$character->character_type->value;
            $lines[] = "Clan: {$clanLabel}";
            $lines[] = "Sect: {$sectLabel}";

            if ($character->generation !== null) {
                $lines[] = 'Generation: '.$character->generation;
            }
            if (is_string($character->nature) && $character->nature !== '') {
                $lines[] = "Nature: {$character->nature}";
            }
            if (is_string($character->demeanor) && $character->demeanor !== '') {
                $lines[] = "Demeanor: {$character->demeanor}";
            }
            if (is_string($character->concept) && $character->concept !== '') {
                $lines[] = "Concept: {$character->concept}";
            }

            if (! $assembly->request->compactIdentity) {
                foreach ($character->disciplines as $row) {
                    if ($row->discipline === null) {
                        continue;
                    }
                    $disciplineIds[] = (int) $row->id;
                    $lines[] = 'Discipline: '.$row->discipline->name.' '.$row->level;
                }
            }
        }

        [$content, $truncated] = $this->trimmer->prefix('## NPC identity', $lines, $tokenBudget);

        return ContextSection::fromContent($this->key(), $content, $this->estimator, [
            'character_id' => $character?->id,
            'entity_id' => $entityId,
            'clan' => $clanLabel,
            'sect' => $sectLabel,
            'discipline_ids' => $disciplineIds,
            'compact' => $assembly->request->compactIdentity,
        ], $truncated ? 'lines_tail' : null);
    }

    private function canonName(mixed $name): string
    {
        if (! is_string($name) || trim($name) === '') {
            return 'unknown';
        }

        return trim($name);
    }
}
