<?php

namespace App\Context\Providers;

use App\Context\ContextAssembly;
use App\Context\ContextSection;
use App\Context\LineTrimmer;
use App\Context\TokenEstimator;
use App\Models\CharacterDiscipline;

class NpcIdentityProvider implements ContextProvider
{
    public function __construct(
        private TokenEstimator $estimator,
        private LineTrimmer $trimmer,
    ) {}

    public function key(): string
    {
        return 'npc_identity';
    }

    public function assemble(ContextAssembly $assembly, int $tokenBudget): ContextSection
    {
        $npcName = $assembly->request->npcName;
        $lines = ["NPC: {$npcName}"];
        $statIds = [];
        $disciplineIds = [];
        $entityId = $assembly->entity?->id;
        $character = $assembly->character;

        if ($character !== null) {
            $canonical = $assembly->entity?->canonical_name;
            if (is_string($canonical) && $canonical !== '' && $canonical !== $npcName) {
                $lines[] = "Canonical name: {$canonical}";
            }

            $lines[] = 'Type: '.$character->character_type->value;

            if ($character->clan_id !== null) {
                $character->loadMissing('clan');
                $clanName = $character->clan?->name;
                if (is_string($clanName) && $clanName !== '') {
                    $lines[] = "Clan: {$clanName}";
                }
                $clanWeakness = $character->clan?->weakness;
                if (is_string($clanWeakness) && $clanWeakness !== '') {
                    $lines[] = "Clan weakness: {$clanWeakness}";
                }
            }

            if ($character->sect_id !== null) {
                $character->loadMissing('sect');
                $sectName = $character->sect?->name;
                if (is_string($sectName) && $sectName !== '') {
                    $lines[] = "Sect: {$sectName}";
                }
            }

            $clan = $character->clan;
            if ($clan !== null && is_string($clan->weakness) && $clan->weakness !== '') {
                $lines[] = "Clan weakness: {$clan->weakness}";
            }

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
                $character->loadMissing('disciplines.discipline');
                foreach ($character->disciplines as $row) {
                    if (! $row instanceof CharacterDiscipline || $row->discipline === null) {
                        continue;
                    }
                    $disciplineIds[] = (int) $row->id;
                    $lines[] = 'Discipline: '.$row->discipline->name.' '.$row->level;
                }
            }
            if ($character->sect_id !== null) {
                $character->loadMissing('sect');
                $lines[] = "Sect: {$character->sect?->name}";
            }

            $lines[] = "Clan weakness: {$clan->weakness}";
        }

        [$content, $truncated] = $this->trimmer->prefix('## NPC identity', $lines, $tokenBudget);

        return ContextSection::fromContent($this->key(), $content, $this->estimator, [
            'character_id' => $character?->id,
            'entity_id' => $entityId,
            'discipline_ids' => $disciplineIds,
            'compact' => $assembly->request->compactIdentity,
        ], $truncated ? 'lines_tail' : null);
    }

}
