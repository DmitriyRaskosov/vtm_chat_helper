<?php

namespace App\Context\Providers;

use App\Context\ContextAssembly;
use App\Context\ContextSection;
use App\Context\LineTrimmer;
use App\Context\TokenEstimator;
use App\Models\CharacterAffiliation;
use App\Models\CharacterRelationship;
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

        $affiliations = CharacterAffiliation::query()
            ->where('character_id', $character->id)
            ->get()
            ->keyBy('id');

        $incoming = CharacterRelationship::query()
            ->where('target_character_id', $character->id)
            ->whereNotIn('id', $neighbors->pluck('id'))
            ->get();

        $extraRelations = WorldRelation::query()
            ->with(['source', 'target', 'type'])
            ->whereIn('id', $incoming->pluck('id'))
            ->get()
            ->keyBy('id');

        $lines = [];
        $relationIds = [];
        $affiliationIds = [];
        $relationshipIds = [];
        $entityIds = [(int) $entity->id];

        foreach ($neighbors as $relation) {
            $relationIds[] = (int) $relation->id;
            $line = $this->formatRelation($entity, $relation);
            $affiliation = $affiliations->get($relation->id);
            if ($affiliation instanceof CharacterAffiliation) {
                $affiliationIds[] = (int) $affiliation->id;
                $line .= $this->formatAffiliationMetrics($affiliation);
            }
            if ($this->isCharacterRelationship($relation)) {
                $relationshipIds[] = (int) $relation->id;
            }
            $entityIds[] = (int) $relation->source_entity_id;
            $entityIds[] = (int) $relation->target_entity_id;
            $lines[] = $line;
        }

        foreach ($incoming as $relationship) {
            $relation = $extraRelations->get($relationship->id);
            if (! $relation instanceof WorldRelation) {
                continue;
            }
            $relationIds[] = (int) $relation->id;
            $relationshipIds[] = (int) $relationship->id;
            $entityIds[] = (int) $relation->source_entity_id;
            $entityIds[] = (int) $relation->target_entity_id;
            $lines[] = $this->formatRelation($entity, $relation);
        }

        if ($lines === []) {
            return ContextSection::omitted($this->key(), [
                'character_id' => (int) $character->id,
                'entity_id' => (int) $entity->id,
                'reason' => 'empty',
            ]);
        }

        [$content, $truncated] = $this->trimmer->prefix('## Direct relations', $lines, $tokenBudget);

        return ContextSection::fromContent($this->key(), $content, $this->estimator, [
            'character_id' => (int) $character->id,
            'entity_id' => (int) $entity->id,
            'relation_ids' => array_values(array_unique($relationIds)),
            'affiliation_ids' => $affiliationIds,
            'relationship_ids' => array_values(array_unique($relationshipIds)),
            'entity_ids' => array_values(array_unique($entityIds)),
        ], $truncated ? 'extra_edges' : null);
    }

    private function formatRelation(WorldEntity $self, WorldRelation $relation): string
    {
        $counterpart = (int) $relation->source_entity_id === (int) $self->id
            ? $relation->target
            : $relation->source;
        $selfName = $self->canonical_name;
        $otherName = $counterpart?->canonical_name ?? ('#'.$relation->target_entity_id);
        $type = $relation->type?->key ?? 'related';
        $weight = number_format((float) $relation->weight, 2);
        $note = is_string($relation->note) && $relation->note !== '' ? '; '.$relation->note : '';

        if ((int) $relation->source_entity_id === (int) $self->id) {
            return "{$selfName} {$type} {$otherName} (weight {$weight}){$note}";
        }

        return "{$otherName} {$type} {$selfName} (weight {$weight}){$note}";
    }

    private function formatAffiliationMetrics(CharacterAffiliation $affiliation): string
    {
        $parts = [
            'stance '.$affiliation->stance->value,
            'trust '.$affiliation->trust,
            'loyalty '.$affiliation->loyalty,
            'fear '.$affiliation->fear,
            'obligation '.$affiliation->obligation,
        ];
        if (is_string($affiliation->role) && $affiliation->role !== '') {
            $parts[] = 'role '.$affiliation->role;
        }
        if (is_string($affiliation->rank) && $affiliation->rank !== '') {
            $parts[] = 'rank '.$affiliation->rank;
        }

        return ' ['.implode(', ', $parts).']';
    }

    private function isCharacterRelationship(WorldRelation $relation): bool
    {
        $sourceType = $relation->source?->entity_type?->value;
        $targetType = $relation->target?->entity_type?->value;

        return $sourceType === 'character' && $targetType === 'character';
    }
}
