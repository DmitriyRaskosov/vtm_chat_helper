<?php

namespace App\Extractor;

use App\Enums\CharacterMemoryNodeType;
use App\Enums\ExtractionCandidateStatus;
use App\Enums\ExtractionRunStatus;
use App\Enums\ExtractionSourceType;
use App\Enums\WorldEntityStatus;
use App\Enums\WorldEntityType;
use App\Enums\WorldEventParticipantRole;
use App\Enums\WorldEventStatus;
use App\Enums\WorldEventType;
use App\Enums\WorldEventVisibility;
use App\Memory\CharacterMemoryService;
use App\Models\Character;
use App\Models\Chronicle;
use App\Models\ExtractionRun;
use App\Models\Message;
use App\Models\Scene;
use App\Models\User;
use App\Models\WorldEntity;
use App\Models\WorldEvent;
use App\Models\WorldRelation;
use App\Models\WorldRelationType;
use App\World\AliasNormalizer;
use App\World\WorldEntityService;
use App\World\WorldEventService;
use App\World\WorldRelationException;
use App\World\WorldRelationService;
use App\World\WorldRelationTypeException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ExtractionCandidateService
{
    /**
     * @var list<WorldEntityType>
     */
    private const DIRECTORY_KINDS = [
        WorldEntityType::Faction,
        WorldEntityType::Clan,
        WorldEntityType::Coterie,
        WorldEntityType::Circle,
        WorldEntityType::Other,
        WorldEntityType::Location,
        WorldEntityType::Item,
        WorldEntityType::Concept,
    ];

    public function __construct(
        private WorldEntityService $entities,
        private WorldRelationService $relations,
        private CharacterMemoryService $memory,
        private WorldEventService $events,
        private ExtractionCandidateMatcher $matcher,
    ) {}

    public function accept(ExtractionRun $run, string $type, int $index): ExtractionRun
    {
        $this->assertRunActionable($run);

        return match ($type) {
            'mention' => $this->acceptMention($run, $index),
            'relation' => $this->acceptRelation($run, $index),
            'memory' => $this->acceptMemory($run, $index),
            'event' => $this->acceptEvent($run, $index),
            default => throw new InvalidArgumentException('Invalid candidate type.'),
        };
    }

    public function discard(ExtractionRun $run, string $type, int $index): ExtractionRun
    {
        $this->assertRunActionable($run);

        return match ($type) {
            'mention' => $this->discardMention($run, $index),
            'relation' => $this->discardRelation($run, $index),
            'memory' => $this->discardMemory($run, $index),
            'event' => $this->discardEvent($run, $index),
            default => throw new InvalidArgumentException('Invalid candidate type.'),
        };
    }

    /**
     * @param  array{name?: string, kind?: string, aliases?: list<string>, alias_of_entity_id?: int|null, sect_faction_id?: int|null}  $patch
     */
    public function patchMention(ExtractionRun $run, int $index, array $patch): ExtractionRun
    {
        $this->assertRunActionable($run);
        $this->assertGraphSource($run);

        return DB::transaction(function () use ($run, $index, $patch): ExtractionRun {
            $run = ExtractionRun::query()->lockForUpdate()->findOrFail($run->id);
            $candidates = $run->candidates;
            $candidate = $this->findMentionCandidate($candidates, $index);

            $this->assertPending($candidate);

            $chronicle = Chronicle::query()->findOrFail($run->chronicle_id);

            if (isset($patch['name'])) {
                $name = trim($patch['name']);
                if ($name === '') {
                    throw new InvalidArgumentException('A name is required.');
                }
                $candidate['name'] = $name;
            }

            if (isset($patch['kind'])) {
                $kind = WorldEntityType::tryFrom($patch['kind']);
                if ($kind === null || ! in_array($kind, self::DIRECTORY_KINDS, true)) {
                    throw new InvalidArgumentException(
                        'Lore extractor cannot create characters or events in the world directory.',
                    );
                }
                $candidate['kind'] = $kind->value;
                if (! in_array($kind, [WorldEntityType::Clan, WorldEntityType::Coterie, WorldEntityType::Circle], true)) {
                    unset($candidate['sect_faction_id']);
                }
            }

            if (array_key_exists('sect_faction_id', $patch)) {
                $kind = WorldEntityType::tryFrom((string) ($candidate['kind'] ?? ''));
                if ($kind === null || ! in_array($kind, [WorldEntityType::Clan, WorldEntityType::Coterie, WorldEntityType::Circle], true)) {
                    unset($candidate['sect_faction_id']);
                } else {
                    $sectId = $patch['sect_faction_id'];
                    if ($sectId === null) {
                        unset($candidate['sect_faction_id']);
                    } else {
                        $sect = WorldEntity::query()->findOrFail((int) $sectId);
                        $this->entities->assertSameChronicle($chronicle, $sect);
                        if ($sect->entity_type !== WorldEntityType::Faction || $sect->status !== WorldEntityStatus::Active) {
                            throw new InvalidArgumentException('Sect must be an active faction.');
                        }
                        $candidate['sect_faction_id'] = (int) $sect->id;
                    }
                }
            }

            if (array_key_exists('aliases', $patch)) {
                $candidate['aliases'] = $this->normalizeAliasList(
                    is_array($patch['aliases']) ? $patch['aliases'] : [],
                    (string) ($candidate['name'] ?? ''),
                );
            }

            if (array_key_exists('alias_of_entity_id', $patch)) {
                $aliasOf = $patch['alias_of_entity_id'];
                if ($aliasOf === null) {
                    unset($candidate['alias_of_entity_id']);
                } else {
                    $entity = WorldEntity::query()->findOrFail((int) $aliasOf);
                    $this->entities->assertSameChronicle($chronicle, $entity);
                    if ($entity->status !== WorldEntityStatus::Active) {
                        throw new InvalidArgumentException('Alias target must be an active entity.');
                    }
                    $candidate['alias_of_entity_id'] = (int) $entity->id;
                }
            }

            $candidate = $this->matcher->rematchMention($candidate, $chronicle);
            $candidates['mentions'] = $this->replaceMention($candidates['mentions'] ?? [], $index, $candidate);
            $candidates = $this->refreshRelationEndpointFlags($candidates, $chronicle);

            $run->candidates = $candidates;
            $run->save();

            return $this->finalizeRun($run);
        });
    }

    /**
     * @param  array<string, mixed>  $candidates
     */
    private function saveCandidates(ExtractionRun $run, array $candidates): ExtractionRun
    {
        $run->candidates = $candidates;
        $run->save();

        return $this->finalizeRun($run);
    }

    private function finalizeRun(ExtractionRun $run): ExtractionRun
    {
        return ExtractionRunCompletion::finalizeIfComplete($run);
    }

    private function acceptMention(ExtractionRun $run, int $index): ExtractionRun
    {
        $this->assertGraphSource($run);

        return DB::transaction(function () use ($run, $index): ExtractionRun {
            $run = ExtractionRun::query()->lockForUpdate()->findOrFail($run->id);
            $candidates = $run->candidates;
            $candidate = $this->findMentionCandidate($candidates, $index);

            $this->assertPending($candidate);
            $chronicle = Chronicle::query()->findOrFail($run->chronicle_id);

            if (isset($candidate['alias_of_entity_id'])) {
                $entity = WorldEntity::query()->findOrFail((int) $candidate['alias_of_entity_id']);
                $this->entities->assertSameChronicle($chronicle, $entity);
                if ($entity->status !== WorldEntityStatus::Active) {
                    throw new InvalidArgumentException('Alias target must be an active entity.');
                }

                $mentionName = (string) $candidate['name'];
                if (AliasNormalizer::normalize($mentionName) !== AliasNormalizer::normalize((string) $entity->canonical_name)) {
                    $this->entities->addAka($entity, $mentionName);
                }
                foreach ($this->normalizeAliasList($candidate['aliases'] ?? [], $mentionName) as $alias) {
                    $this->entities->addAka($entity, $alias);
                }

                $candidate['status'] = ExtractionCandidateStatus::Merged->value;
                $candidate['matched_entity_id'] = (int) $entity->id;
            } elseif (($candidate['candidate_type'] ?? null) === 'new_entity') {
                $kind = WorldEntityType::tryFrom((string) ($candidate['kind'] ?? ''));
                if ($kind === null || ! in_array($kind, self::DIRECTORY_KINDS, true)) {
                    throw new InvalidArgumentException(
                        'Lore extractor cannot create characters or events in the world directory.',
                    );
                }

                $name = (string) $candidate['name'];
                $typed = $this->entities->defaultTypedPayload($kind);
                if (isset($candidate['sect_faction_id'])) {
                    $typed['sect_faction_id'] = (int) $candidate['sect_faction_id'];
                }
                try {
                    $entity = $this->entities->create(
                        $chronicle,
                        $kind,
                        $name,
                        aliases: $this->normalizeAliasList($candidate['aliases'] ?? [], $name),
                        typed: $typed,
                    );
                } catch (UniqueConstraintViolationException) {
                    throw new InvalidArgumentException('This name is already used in the chronicle.');
                }

                $candidate['status'] = ExtractionCandidateStatus::Accepted->value;
                $candidate['created_entity_id'] = (int) $entity->id;
            } else {
                if (! isset($candidate['matched_entity_id'])) {
                    throw new InvalidArgumentException('Mention has no matched entity.');
                }

                $candidate['status'] = ExtractionCandidateStatus::Accepted->value;
            }

            $candidates['mentions'] = $this->replaceMention($candidates['mentions'] ?? [], $index, $candidate);
            $candidates = $this->refreshRelationEndpointFlags($candidates, $chronicle);

            return $this->saveCandidates($run, $candidates);
        });
    }

    private function acceptRelation(ExtractionRun $run, int $index): ExtractionRun
    {
        $this->assertGraphSource($run);

        return DB::transaction(function () use ($run, $index): ExtractionRun {
            $run = ExtractionRun::query()->lockForUpdate()->findOrFail($run->id);
            $candidates = $run->candidates;
            $candidate = $this->findRelationCandidate($candidates, $index);

            $this->assertPending($candidate);

            $chronicle = Chronicle::query()->findOrFail($run->chronicle_id);
            $source = $this->resolveEndpoint($chronicle, $candidates, (string) $candidate['source']);
            $target = $this->resolveEndpoint($chronicle, $candidates, (string) $candidate['target']);

            if ($source === null || $target === null) {
                throw new InvalidArgumentException(
                    'Both relation endpoints must be matched or accepted as new entities in this run.',
                );
            }

            $type = WorldRelationType::query()
                ->where('key', $candidate['key'])
                ->where('enabled', true)
                ->first();

            if ($type === null) {
                throw new InvalidArgumentException('Relation type is not enabled.');
            }

            try {
                $relation = $this->relations->relate(
                    $source,
                    $target,
                    $type,
                    provenance: [
                        'extraction_run_id' => $run->id,
                        'candidate_type' => 'relation',
                        'candidate_index' => $index,
                    ],
                );

                $candidate['status'] = ExtractionCandidateStatus::Accepted->value;
                $candidate['relation_id'] = (int) $relation->id;
                $candidate['endpoints_resolved'] = true;
                $candidate['source_matched_entity_id'] = (int) $source->id;
                $candidate['target_matched_entity_id'] = (int) $target->id;
            } catch (WorldRelationException $e) {
                $existing = $this->findActiveRelation($source, $target, $type);
                if ($existing === null) {
                    throw $e;
                }

                $candidate['status'] = ExtractionCandidateStatus::Merged->value;
                $candidate['relation_id'] = (int) $existing->id;
                $candidate['endpoints_resolved'] = true;
                $candidate['source_matched_entity_id'] = (int) $source->id;
                $candidate['target_matched_entity_id'] = (int) $target->id;
            } catch (WorldRelationTypeException $e) {
                throw new InvalidArgumentException($e->getMessage(), 0, $e);
            }

            $candidates['relations'] = $this->replaceRelation($candidates['relations'] ?? [], $index, $candidate);

            return $this->saveCandidates($run, $candidates);
        });
    }

    private function acceptMemory(ExtractionRun $run, int $index): ExtractionRun
    {
        $this->assertBiographySource($run);

        return DB::transaction(function () use ($run, $index): ExtractionRun {
            $run = ExtractionRun::query()->lockForUpdate()->findOrFail($run->id);
            $candidates = $run->candidates;
            $candidate = $this->findMemoryCandidate($candidates, $index);

            $this->assertPending($candidate);

            $character = Character::query()->findOrFail($run->source_id);
            if ((int) $character->chronicle_id !== (int) $run->chronicle_id) {
                throw new InvalidArgumentException('Character does not belong to this chronicle.');
            }

            $nodeType = CharacterMemoryNodeType::tryFrom((string) ($candidate['node_type'] ?? ''))
                ?? CharacterMemoryNodeType::Event;

            $author = User::query()->find($run->user_id);

            $node = $this->memory->remember(
                $character,
                (string) $candidate['text'],
                $nodeType,
                provenance: [
                    'extraction_run_id' => $run->id,
                    'candidate_type' => 'memory',
                    'candidate_index' => $index,
                ],
                author: $author,
            );

            $candidate['status'] = ExtractionCandidateStatus::Accepted->value;
            $candidate['memory_node_id'] = (int) $node->id;
            $candidates['memories'] = $this->replaceMemory($candidates['memories'] ?? [], $index, $candidate);

            return $this->saveCandidates($run, $candidates);
        });
    }

    private function acceptEvent(ExtractionRun $run, int $index): ExtractionRun
    {
        $this->assertSceneSource($run);

        return DB::transaction(function () use ($run, $index): ExtractionRun {
            $run = ExtractionRun::query()->lockForUpdate()->findOrFail($run->id);
            $candidates = $run->candidates;
            $candidate = $this->findEventCandidate($candidates, $index);

            $this->assertPending($candidate);

            $chronicle = Chronicle::query()->findOrFail($run->chronicle_id);
            $scene = Scene::query()->findOrFail($run->source_id);
            $approver = User::query()->findOrFail($run->user_id);

            $summary = trim((string) ($candidate['summary'] ?? ''));
            $entity = $this->entities->create(
                $chronicle,
                WorldEntityType::Event,
                (string) $candidate['title'],
                shortDescription: $summary !== '' ? $summary : null,
                typed: [
                    'event_type' => WorldEventType::Other,
                    'description' => $summary,
                    'status' => WorldEventStatus::Proposed,
                    'scene_id' => $scene->id,
                    'importance' => 0,
                    'visibility' => WorldEventVisibility::Public,
                ],
            );

            $event = WorldEvent::query()->findOrFail($entity->id);

            foreach ($candidate['participants'] ?? [] as $participant) {
                $entityId = $participant['matched_entity_id'] ?? null;
                if ($entityId === null) {
                    continue;
                }

                $participantEntity = WorldEntity::query()->find((int) $entityId);
                if ($participantEntity === null) {
                    continue;
                }

                $role = WorldEventParticipantRole::tryFrom((string) ($participant['role'] ?? ''))
                    ?? WorldEventParticipantRole::Actor;

                $this->events->addParticipant($event, $participantEntity, $role);
            }

            $this->events->addSource($event, scene: $scene);

            $messageIds = Message::query()
                ->where('scene_id', $scene->id)
                ->whereIn('id', $candidate['source_message_ids'] ?? [])
                ->pluck('id');

            foreach ($messageIds as $messageId) {
                $message = Message::query()->find($messageId);
                if ($message === null) {
                    continue;
                }

                $this->events->addSource(
                    $event,
                    message: $message,
                    excerpt: Str::limit((string) $message->body, 500),
                );
            }

            $approved = $this->events->approve($event, $approver);

            $candidate['status'] = ExtractionCandidateStatus::Accepted->value;
            $candidate['created_entity_id'] = (int) $entity->id;
            $candidate['world_event_id'] = (int) $approved->id;

            $candidates['events'] = $this->replaceEvent($candidates['events'] ?? [], $index, $candidate);
            $candidates = $this->refreshRelationEndpointFlags($candidates, $chronicle);

            return $this->saveCandidates($run, $candidates);
        });
    }

    private function discardMention(ExtractionRun $run, int $index): ExtractionRun
    {
        $this->assertGraphSource($run);

        return $this->discardCandidate($run, 'mention', $index);
    }

    private function discardRelation(ExtractionRun $run, int $index): ExtractionRun
    {
        $this->assertGraphSource($run);

        return $this->discardCandidate($run, 'relation', $index);
    }

    private function discardEvent(ExtractionRun $run, int $index): ExtractionRun
    {
        $this->assertSceneSource($run);

        return $this->discardCandidate($run, 'event', $index);
    }

    private function discardMemory(ExtractionRun $run, int $index): ExtractionRun
    {
        $this->assertBiographySource($run);

        return $this->discardCandidate($run, 'memory', $index);
    }

    private function discardCandidate(ExtractionRun $run, string $type, int $index): ExtractionRun
    {
        return DB::transaction(function () use ($run, $type, $index): ExtractionRun {
            $run = ExtractionRun::query()->lockForUpdate()->findOrFail($run->id);
            $candidates = $run->candidates;

            if ($type === 'mention') {
                $candidate = $this->findMentionCandidate($candidates, $index);
                $this->assertPending($candidate);
                $candidate['status'] = ExtractionCandidateStatus::Discarded->value;
                $candidates['mentions'] = $this->replaceMention($candidates['mentions'] ?? [], $index, $candidate);
                $chronicle = Chronicle::query()->findOrFail($run->chronicle_id);
                $candidates = $this->refreshRelationEndpointFlags($candidates, $chronicle);
            } elseif ($type === 'relation') {
                $candidate = $this->findRelationCandidate($candidates, $index);
                $this->assertPending($candidate);
                $candidate['status'] = ExtractionCandidateStatus::Discarded->value;
                $candidates['relations'] = $this->replaceRelation($candidates['relations'] ?? [], $index, $candidate);
            } elseif ($type === 'event') {
                $candidate = $this->findEventCandidate($candidates, $index);
                $this->assertPending($candidate);
                $candidate['status'] = ExtractionCandidateStatus::Discarded->value;
                $candidates['events'] = $this->replaceEvent($candidates['events'] ?? [], $index, $candidate);
                $chronicle = Chronicle::query()->findOrFail($run->chronicle_id);
                $candidates = $this->refreshRelationEndpointFlags($candidates, $chronicle);
            } else {
                $candidate = $this->findMemoryCandidate($candidates, $index);
                $this->assertPending($candidate);
                $candidate['status'] = ExtractionCandidateStatus::Discarded->value;
                $candidates['memories'] = $this->replaceMemory($candidates['memories'] ?? [], $index, $candidate);
            }

            return $this->saveCandidates($run, $candidates);
        });
    }

    private function assertRunActionable(ExtractionRun $run): void
    {
        $status = $run->status ?? ExtractionRunStatus::NeedsReview;

        if ($status === ExtractionRunStatus::Superseded || $status === ExtractionRunStatus::Failed) {
            throw new InvalidArgumentException('This extraction run is no longer actionable.');
        }

        if (in_array($status, [ExtractionRunStatus::Queued, ExtractionRunStatus::Running], true)) {
            throw new InvalidArgumentException('Extraction run is still in progress.');
        }
    }

    private function assertGraphSource(ExtractionRun $run): void
    {
        if (! in_array($run->source_type, [ExtractionSourceType::Lore, ExtractionSourceType::Scene], true)) {
            throw new InvalidArgumentException('This action is only supported for lore or scene extraction runs.');
        }
    }

    private function assertSceneSource(ExtractionRun $run): void
    {
        if ($run->source_type !== ExtractionSourceType::Scene) {
            throw new InvalidArgumentException('This action is only supported for scene extraction runs.');
        }
    }

    private function assertBiographySource(ExtractionRun $run): void
    {
        if ($run->source_type !== ExtractionSourceType::Biography) {
            throw new InvalidArgumentException('This action is only supported for biography extraction runs.');
        }
    }

    /**
     * @param  array<string, mixed>  $candidate
     */
    private function assertPending(array $candidate): void
    {
        $status = (string) ($candidate['status'] ?? '');
        if ($status !== ExtractionCandidateStatus::Pending->value) {
            throw new InvalidArgumentException('Candidate is not pending.');
        }
    }

    /**
     * @param  array<string, mixed>  $candidates
     * @return array<string, mixed>
     */
    private function findMentionCandidate(array $candidates, int $index): array
    {
        foreach ($candidates['mentions'] ?? [] as $candidate) {
            if ((int) ($candidate['index'] ?? -1) === $index) {
                return $candidate;
            }
        }

        throw new InvalidArgumentException('Mention candidate not found.');
    }

    /**
     * @param  array<string, mixed>  $candidates
     * @return array<string, mixed>
     */
    private function findRelationCandidate(array $candidates, int $index): array
    {
        foreach ($candidates['relations'] ?? [] as $candidate) {
            if ((int) ($candidate['index'] ?? -1) === $index) {
                return $candidate;
            }
        }

        throw new InvalidArgumentException('Relation candidate not found.');
    }

    /**
     * @param  array<string, mixed>  $candidates
     * @return array<string, mixed>
     */
    private function findMemoryCandidate(array $candidates, int $index): array
    {
        foreach ($candidates['memories'] ?? [] as $candidate) {
            if ((int) ($candidate['index'] ?? -1) === $index) {
                return $candidate;
            }
        }

        throw new InvalidArgumentException('Memory candidate not found.');
    }

    /**
     * @param  array<string, mixed>  $candidates
     * @return array<string, mixed>
     */
    private function findEventCandidate(array $candidates, int $index): array
    {
        foreach ($candidates['events'] ?? [] as $candidate) {
            if ((int) ($candidate['index'] ?? -1) === $index) {
                return $candidate;
            }
        }

        throw new InvalidArgumentException('Event candidate not found.');
    }

    /**
     * @param  list<array<string, mixed>>  $mentions
     * @param  array<string, mixed>  $candidate
     * @return list<array<string, mixed>>
     */
    private function replaceMention(array $mentions, int $index, array $candidate): array
    {
        return array_map(
            fn (array $row): array => (int) ($row['index'] ?? -1) === $index ? $candidate : $row,
            $mentions,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $relations
     * @param  array<string, mixed>  $candidate
     * @return list<array<string, mixed>>
     */
    private function replaceRelation(array $relations, int $index, array $candidate): array
    {
        return array_map(
            fn (array $row): array => (int) ($row['index'] ?? -1) === $index ? $candidate : $row,
            $relations,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $memories
     * @param  array<string, mixed>  $candidate
     * @return list<array<string, mixed>>
     */
    private function replaceMemory(array $memories, int $index, array $candidate): array
    {
        return array_map(
            fn (array $row): array => (int) ($row['index'] ?? -1) === $index ? $candidate : $row,
            $memories,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $events
     * @param  array<string, mixed>  $candidate
     * @return list<array<string, mixed>>
     */
    private function replaceEvent(array $events, int $index, array $candidate): array
    {
        return array_map(
            fn (array $row): array => (int) ($row['index'] ?? -1) === $index ? $candidate : $row,
            $events,
        );
    }

    /**
     * @param  array<string, mixed>  $candidates
     * @return array<string, mixed>
     */
    private function refreshRelationEndpointFlags(array $candidates, Chronicle $chronicle): array
    {
        $relations = [];

        foreach ($candidates['relations'] ?? [] as $relation) {
            if (($relation['status'] ?? '') !== ExtractionCandidateStatus::Pending->value) {
                $relations[] = $relation;

                continue;
            }

            $source = $this->resolveEndpoint($chronicle, $candidates, (string) ($relation['source'] ?? ''));
            $target = $this->resolveEndpoint($chronicle, $candidates, (string) ($relation['target'] ?? ''));
            $relation['endpoints_resolved'] = $source !== null && $target !== null;

            if ($source !== null) {
                $relation['source_matched_entity_id'] = (int) $source->id;
            }

            if ($target !== null) {
                $relation['target_matched_entity_id'] = (int) $target->id;
            }

            $relations[] = $this->matcher->validateRelationCandidate($relation, $source, $target);
        }

        $candidates['relations'] = $relations;

        return $candidates;
    }

    /**
     * @param  array<string, mixed>  $candidates
     */
    private function resolveEndpoint(Chronicle $chronicle, array $candidates, string $name): ?WorldEntity
    {
        $normalized = AliasNormalizer::normalize($name);

        foreach ($candidates['mentions'] ?? [] as $mention) {
            if (AliasNormalizer::normalize((string) ($mention['name'] ?? '')) !== $normalized) {
                continue;
            }

            $status = (string) ($mention['status'] ?? '');
            if ($status === ExtractionCandidateStatus::Pending->value && isset($mention['alias_of_entity_id'])) {
                return WorldEntity::query()->find((int) $mention['alias_of_entity_id']);
            }

            if (in_array($status, [ExtractionCandidateStatus::Accepted->value, ExtractionCandidateStatus::Merged->value], true)) {
                $entityId = $mention['created_entity_id'] ?? $mention['matched_entity_id'] ?? null;
                if ($entityId !== null) {
                    return WorldEntity::query()->find((int) $entityId);
                }
            }
        }

        foreach ($candidates['events'] ?? [] as $event) {
            if (AliasNormalizer::normalize((string) ($event['title'] ?? '')) !== $normalized) {
                continue;
            }

            if (($event['status'] ?? '') === ExtractionCandidateStatus::Accepted->value) {
                $entityId = $event['created_entity_id'] ?? $event['world_event_id'] ?? null;
                if ($entityId !== null) {
                    return WorldEntity::query()->find((int) $entityId);
                }
            }
        }

        return $this->entities->findByAlias($chronicle, $name);
    }

    /**
     * @param  list<mixed>  $raw
     * @return list<string>
     */
    private function normalizeAliasList(array $raw, string $canonicalName): array
    {
        $canonical = AliasNormalizer::normalize($canonicalName);
        $unique = [];

        foreach ($raw as $item) {
            if (! is_string($item)) {
                continue;
            }

            $alias = trim($item);
            if ($alias === '') {
                continue;
            }

            $normalized = AliasNormalizer::normalize($alias);
            if ($canonical !== '' && $normalized === $canonical) {
                continue;
            }

            $unique[$normalized] = $alias;
        }

        return array_values($unique);
    }

    private function findActiveRelation(
        WorldEntity $source,
        WorldEntity $target,
        WorldRelationType $type,
    ): ?WorldRelation {
        return WorldRelation::query()
            ->where('chronicle_id', $source->chronicle_id)
            ->where('relation_type_id', $type->id)
            ->whereNull('ended_at')
            ->where(function ($query) use ($source, $target, $type): void {
                $query->where(function ($inner) use ($source, $target): void {
                    $inner->where('source_entity_id', $source->id)
                        ->where('target_entity_id', $target->id);
                });

                if ($type->symmetric) {
                    $query->orWhere(function ($inner) use ($source, $target): void {
                        $inner->where('source_entity_id', $target->id)
                            ->where('target_entity_id', $source->id);
                    });
                }
            })
            ->first();
    }
}
