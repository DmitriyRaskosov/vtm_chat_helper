<?php

namespace App\Context\Providers;

use App\Context\ContextAssembly;
use App\Context\ContextSection;
use App\Context\LineTrimmer;
use App\Context\TokenEstimator;
use App\Models\WorldEntity;
use App\Models\WorldRelation;
use App\World\WorldRelationService;

class DirectRelationsProvider implements ContextProvider
{
    public function __construct(
        private TokenEstimator $estimator,
        private LineTrimmer $trimmer,
        private WorldRelationService $relations,
    ) {}

    public function key(): string
    {
        return 'direct_relations';
    }

    public function assemble(ContextAssembly $assembly, int $tokenBudget): ContextSection
    {
        $character = $assembly->character;
        $entity = $assembly->entity;
        if ($character === null || $entity === null) {
            return ContextSection::omitted($this->key(), ['reason' => 'no_character']);
        }

        $neighbors = $this->relations->neighbors($entity)
            ->sortByDesc(fn (WorldRelation $relation): float => abs((float) $relation->weight))
            ->values();

        $incomingCharacter = WorldRelation::query()
            ->active()
            ->where('target_type', 'character')
            ->where('target_id', $character->id)
            ->whereNotIn('id', $neighbors->pluck('id'))
            ->with(['source', 'target'])
            ->get();

        $lines = [];
        $relationIds = [];
        $entityIds = [(int) $entity->id];

        foreach ($neighbors as $relation) {
            $relationIds[] = (int) $relation->id;
            $line = $this->formatRelation($entity, $relation);
            $line .= $this->formatMetadata($relation);
            $entityIds[] = (int) $relation->source_id;
            $entityIds[] = (int) $relation->target_id;
            $lines[] = $line;
        }

        foreach ($incomingCharacter as $relation) {
            $relationIds[] = (int) $relation->id;
            $entityIds[] = (int) $relation->source_id;
            $entityIds[] = (int) $relation->target_id;
            $lines[] = $this->formatRelation($entity, $relation);
        }

        if ($lines === []) {
            return ContextSection::omitted($this->key(), [
                'character_id' => (int) $character->id,
                'entity_id' => (int) $entity->id,
                'reason' => 'empty',
            ]);
        }

        $lines = array_map(fn (string $line): string => '[canon] '.$line, $lines);

        [$content, $truncated] = $this->trimmer->prefix('## Direct relations', $lines, $tokenBudget);

        return ContextSection::fromContent($this->key(), $content, $this->estimator, [
            'character_id' => (int) $character->id,
            'entity_id' => (int) $entity->id,
            'relation_ids' => array_values(array_unique($relationIds)),
            'entity_ids' => array_values(array_unique($entityIds)),
        ], $truncated ? 'extra_edges' : null);
    }

    private function formatRelation(WorldEntity $self, WorldRelation $relation): string
    {
        $counterpart = (int) $relation->source_id === (int) $self->id
            ? $relation->target
            : $relation->source;
        $selfName = $self->canonical_name;
        $otherName = $counterpart?->canonical_name ?? ('#'.$relation->target_id);
        $type = $relation->relation;
        $weight = number_format((float) $relation->weight, 2);
        $note = is_string($relation->note) && $relation->note !== '' ? '; '.$relation->note : '';

        if ((int) $relation->source_id === (int) $self->id) {
            return "{$selfName} {$type} {$otherName} (weight {$weight}){$note}";
        }

        return "{$otherName} {$type} {$selfName} (weight {$weight}){$note}";
    }

    private function formatMetadata(WorldRelation $relation): string
    {
        $metadata = is_array($relation->metadata) ? $relation->metadata : [];
        $parts = [];

        if (isset($metadata['stance']) && is_string($metadata['stance']) && $metadata['stance'] !== '') {
            $parts[] = 'stance '.$metadata['stance'];
        }

        foreach (['trust', 'loyalty', 'fear', 'obligation'] as $metric) {
            if (array_key_exists($metric, $metadata)) {
                $parts[] = $metric.' '.$metadata[$metric];
            }
        }

        foreach (['role', 'rank'] as $field) {
            if (isset($metadata[$field]) && is_string($metadata[$field]) && $metadata[$field] !== '') {
                $parts[] = $field.' '.$metadata[$field];
            }
        }

        if ($relation->intensity !== null) {
            $parts[] = 'intensity '.$relation->intensity;
        }

        return $parts === [] ? '' : ' ['.implode(', ', $parts).']';
    }
}
