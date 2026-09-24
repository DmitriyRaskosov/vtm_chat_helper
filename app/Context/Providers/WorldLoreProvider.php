<?php

namespace App\Context\Providers;

use App\Context\ContextAssembly;
use App\Context\ContextSection;
use App\Context\LineTrimmer;
use App\Context\TokenEstimator;

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

        $filters = config('retrieval.world_graphrag');
        $bundle = $this->graph->expandForNpc($character, $assembly->request->retrievalQuery(), $assembly->scene);
        $selfId = (int) $character->id;

        $lines = [];
        $entityIds = [];
        foreach ($bundle->entities as $entity) {
            if (! $entity instanceof WorldGraphEntity || $entity->id === $selfId) {
                continue;
            }
            $entityIds[] = $entity->id;
            $seed = $entity->seed ? ', seed' : '';
            $description = is_string($entity->shortDescription) && $entity->shortDescription !== ''
                ? ': '.$entity->shortDescription
                : '';
            $lines[] = '[canon] '.$entity->canonicalName.' ('.$entity->entityType.', depth '.$entity->depth.$seed.')'.$description;
        }

        $relationIds = [];
        $names = [];
        foreach ($bundle->entities as $entity) {
            if ($entity instanceof WorldGraphEntity) {
                $names[$entity->id] = $entity->canonicalName;
            }
        }
        $names[$selfId] = $assembly->entity?->canonical_name ?? $assembly->request->npcName;

        foreach ($bundle->relations as $relation) {
            if (! $relation instanceof WorldGraphRelation) {
                continue;
            }
            $relationIds[] = $relation->id;
            $source = $names[$relation->sourceEntityId] ?? '#'.$relation->sourceEntityId;
            $target = $names[$relation->targetEntityId] ?? '#'.$relation->targetEntityId;
            $note = is_string($relation->note) && $relation->note !== '' ? '; '.$relation->note : '';
            $lines[] = '[canon] '.$source.' '.$relation->typeKey.' '.$target.' (w '.number_format($relation->weight, 2).')'.$note;
        }

        $eventIds = [];
        foreach ($bundle->events as $event) {
            $eventIds[] = (int) $event['id'];
            $lines[] = '[canon] Event: '.$event['title'].' ('.$event['event_type'].', importance '.$event['importance'].')';
        }

        $affiliationIds = [];
        foreach ($bundle->affiliations as $affiliation) {
            $affiliationIds[] = (int) $affiliation['id'];
            $who = $names[(int) $affiliation['character_id']] ?? '#'.$affiliation['character_id'];
            $target = $names[(int) $affiliation['target_entity_id']] ?? '#'.$affiliation['target_entity_id'];
            $lines[] = '[canon] '.$who.' affiliated with '.$target
                .' ('.$affiliation['affiliation_type'].', loyalty '.$affiliation['loyalty'].')';
        }

        $loreChunkIds = [];
        $loreEntryIds = [];
        foreach ($bundle->loreChunks as $chunk) {
            $loreChunkIds[] = (int) $chunk['id'];
            $loreEntryIds[] = (int) $chunk['lore_entry_id'];
            $lines[] = '[canon] Known lore: '.$chunk['content'];
        }

        if ($lines === []) {
            return ContextSection::omitted($this->key(), [
                'character_id' => $selfId,
                'chronicle_id' => (int) $assembly->chronicle->id,
                'seed_ids' => $bundle->seedIds,
                'filters' => $filters,
                'reason' => 'empty',
            ]);
        }

        [$content, $truncated] = $this->trimmer->prefix('## World', $lines, $tokenBudget);

        return ContextSection::fromContent($this->key(), $content, $this->estimator, [
            'character_id' => $selfId,
            'chronicle_id' => (int) $assembly->chronicle->id,
            'entity_ids' => $entityIds,
            'relation_ids' => $relationIds,
            'event_ids' => $eventIds,
            'affiliation_ids' => $affiliationIds,
            'lore_chunk_ids' => $loreChunkIds,
            'lore_entry_ids' => array_values(array_unique($loreEntryIds)),
            'seed_ids' => $bundle->seedIds,
            'filters' => $filters,
        ], $truncated ? 'lowest_score' : null);
    }
}
