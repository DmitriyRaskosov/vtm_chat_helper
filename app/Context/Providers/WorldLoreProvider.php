<?php

namespace App\Context\Providers;

use App\Context\ContextAssembly;
use App\Context\ContextSection;
use App\Context\LineTrimmer;
use App\Context\TokenEstimator;
use App\Models\CanonLoreEntry;
use App\Models\SceneParticipant;
use App\Models\WorldEntity;

class WorldLoreProvider implements ContextProvider
{
    public function __construct(
        private TokenEstimator $estimator,
        private LineTrimmer $trimmer,
    ) {}

    public function key(): string
    {
        return 'world_lore';
    }

    public function assemble(ContextAssembly $assembly, int $tokenBudget): ContextSection
    {
        $character = $assembly->character;
        if ($character === null) {
            return ContextSection::omitted($this->key(), ['reason' => 'no_character']);
        }

        $lines = [];
        $loreEntryIds = [];
        $presentIds = [];

        // 1. Участники сцены (кроме самого NPC)
        $participantIds = SceneParticipant::query()
            ->where('scene_id', $assembly->scene->id)
            ->where('character_id', '!=', $character->id)
            ->pluck('character_id');

        if ($participantIds->isNotEmpty()) {
            $names = WorldEntity::query()
                ->whereIn('id', $participantIds)
                ->pluck('canonical_name', 'id');

            foreach ($names as $id => $name) {
                if (! is_string($name) || $name === '') {
                    continue;
                }
                $presentIds[] = (int) $id;
                $lines[] = '[scene] Present: '.$name;
            }
        }

        // 2. Статьи канона, связанные с кланом и сектой
        $canonRefs = [];
        if ($character->clan_id !== null) {
            $canonRefs[] = ['clan', (int) $character->clan_id];
        }
        if ($character->sect_id !== null) {
            $canonRefs[] = ['sect', (int) $character->sect_id];
        }

        foreach ($canonRefs as [$type, $id]) {
            $entries = CanonLoreEntry::query()
                ->whereIn('id', function ($q) use ($type, $id) {
                    $q->select('lore_entry_id')
                        ->from('canon_lore_entry_entities')
                        ->where('entity_type', $type)
                        ->where('entity_id', $id);
                })
                ->orderBy('id')
                ->limit(3)
                ->get(['id', 'title', 'category', 'text']);

            foreach ($entries as $entry) {
                $loreEntryIds[] = (int) $entry->id;
                $excerpt = mb_substr($entry->text, 0, 400);
                if (mb_strlen($entry->text) > 400) {
                    $excerpt .= '…';
                }
                $lines[] = '[canon] '.$entry->title.' ('.$entry->category.'): '.$excerpt;
            }
        }

        if ($lines === []) {
            return ContextSection::omitted($this->key(), [
                'character_id' => (int) $character->id,
                'reason' => 'empty',
            ]);
        }

        [$content, $truncated] = $this->trimmer->prefix('## World', $lines, $tokenBudget);

        return ContextSection::fromContent($this->key(), $content, $this->estimator, [
            'character_id' => (int) $character->id,
            'lore_entry_ids' => $loreEntryIds,
            'present_entity_ids' => $presentIds,
        ], $truncated ? 'tail' : null);
    }
}