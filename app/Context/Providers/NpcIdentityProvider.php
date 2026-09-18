<?php

namespace App\Context\Providers;

use App\Character\CharacterSheetReader;
use App\Context\ContextAssembly;
use App\Context\ContextSection;
use App\Context\LineTrimmer;
use App\Context\TokenEstimator;
use App\Models\CharacterDiscipline;
use App\Models\CharacterStat;

class NpcIdentityProvider implements ContextProvider
{
    public function __construct(
        private TokenEstimator $estimator,
        private LineTrimmer $trimmer,
        private CharacterSheetReader $sheet,
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
                $sheet = $this->sheet->relevant($character);
                foreach ($sheet->stats as $stat) {
                    $statIds[] = (int) $stat->id;
                    $lines[] = $this->formatStat($stat);
                }

                $character->loadMissing('disciplines.discipline');
                foreach ($character->disciplines as $row) {
                    if (! $row instanceof CharacterDiscipline || $row->discipline === null) {
                        continue;
                    }
                    $disciplineIds[] = (int) $row->id;
                    $lines[] = 'Discipline: '.$row->discipline->display_name.' '.$row->level;
                }
            }
        }

        [$content, $truncated] = $this->trimmer->prefix('## NPC identity', $lines, $tokenBudget);

        return ContextSection::fromContent($this->key(), $content, $this->estimator, [
            'character_id' => $character?->id,
            'entity_id' => $entityId,
            'stat_ids' => $statIds,
            'discipline_ids' => $disciplineIds,
            'compact' => $assembly->request->compactIdentity,
        ], $truncated ? 'stats_tail' : null);
    }

    private function formatStat(CharacterStat $stat): string
    {
        $label = is_string($stat->display_name) && $stat->display_name !== ''
            ? $stat->display_name
            : $stat->stat_key;
        $range = $stat->maximum !== null ? $stat->value.'/'.$stat->maximum : (string) $stat->value;
        $specs = $stat->specializations
            ->where('is_active', true)
            ->pluck('name')
            ->filter()
            ->implode(', ');
        $suffix = $specs !== '' ? " ({$specs})" : '';

        return $stat->category->value.' '.$label.': '.$range.$suffix;
    }
}
