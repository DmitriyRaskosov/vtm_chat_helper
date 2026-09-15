<?php

namespace App\Extractor;

use App\Enums\CharacterMemoryNodeType;
use App\Enums\ExtractionCandidateStatus;
use App\Enums\ExtractionSourceType;
use App\Enums\WorldEventParticipantRole;
use App\Enums\WorldEntityType;
use App\Models\Chronicle;
use App\Models\WorldRelationType;
use App\World\WorldEntityService;

class ExtractionCandidateMatcher
{
    public function __construct(private WorldEntityService $entities) {}

    /**
     * @param  array{
     *     mentions: list<array{name: string, kind: string}>,
     *     relations: list<array{source: string, target: string, key: string}>,
     *     events: list<array<string, mixed>>,
     *     memories: list<array<string, mixed>>
     * }  $parsed
     * @return array<string, mixed>
     */
    /**
     * @param  list<int>  $sceneMessageIds
     */
    public function match(
        array $parsed,
        Chronicle $chronicle,
        ExtractionSourceType $sourceType,
        array $sceneMessageIds = [],
    ): array {
        $enabledKeys = WorldRelationType::query()
            ->where('enabled', true)
            ->pluck('key')
            ->all();

        $mentions = [];
        $events = [];
        $memories = [];
        $discarded = [];

        if ($sourceType === ExtractionSourceType::Biography) {
            foreach ($parsed['mentions'] as $index => $mention) {
                $discarded[] = [
                    'index' => $index,
                    'type' => 'mention',
                    'status' => ExtractionCandidateStatus::Discarded->value,
                    'reason' => 'unsupported_for_source',
                    'payload' => $mention,
                ];
            }

            foreach ($parsed['relations'] as $index => $relation) {
                $discarded[] = [
                    'index' => $index,
                    'type' => 'relation',
                    'status' => ExtractionCandidateStatus::Discarded->value,
                    'reason' => 'unsupported_for_source',
                    'payload' => $relation,
                ];
            }
        }

        foreach ($parsed['mentions'] as $index => $mention) {
            if ($sourceType === ExtractionSourceType::Biography) {
                continue;
            }
            $matched = $this->entities->findByAlias($chronicle, $mention['name']);
            $candidate = [
                'index' => $index,
                'name' => $mention['name'],
                'kind' => $this->normalizeKind($mention['kind']),
                'status' => ExtractionCandidateStatus::Pending->value,
            ];

            if ($matched !== null) {
                $candidate['matched_entity_id'] = (int) $matched->id;
            } else {
                $candidate['candidate_type'] = 'new_entity';
            }

            $mentions[] = $candidate;
        }

        $relations = [];

        foreach ($parsed['relations'] as $index => $relation) {
            if ($sourceType === ExtractionSourceType::Biography) {
                continue;
            }

            if (! in_array($relation['key'], $enabledKeys, true)) {
                $discarded[] = [
                    'index' => $index,
                    'type' => 'relation',
                    'status' => ExtractionCandidateStatus::Discarded->value,
                    'reason' => 'invalid_key',
                    'source' => $relation['source'],
                    'target' => $relation['target'],
                    'key' => $relation['key'],
                ];

                continue;
            }

            $sourceMatch = $this->entities->findByAlias($chronicle, $relation['source']);
            $targetMatch = $this->entities->findByAlias($chronicle, $relation['target']);

            $candidate = [
                'index' => $index,
                'source' => $relation['source'],
                'target' => $relation['target'],
                'key' => $relation['key'],
                'status' => ExtractionCandidateStatus::Pending->value,
                'endpoints_resolved' => $sourceMatch !== null && $targetMatch !== null,
            ];

            if ($sourceMatch !== null) {
                $candidate['source_matched_entity_id'] = (int) $sourceMatch->id;
            }

            if ($targetMatch !== null) {
                $candidate['target_matched_entity_id'] = (int) $targetMatch->id;
            }

            $relations[] = $candidate;
        }

        foreach ($parsed['events'] as $index => $event) {
            if ($sourceType !== ExtractionSourceType::Scene) {
                $discarded[] = [
                    'index' => $index,
                    'type' => 'event',
                    'status' => ExtractionCandidateStatus::Discarded->value,
                    'reason' => 'unsupported_for_source',
                    'payload' => $event,
                ];

                continue;
            }

            $title = trim((string) ($event['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            $participants = [];
            foreach ($event['participants'] ?? [] as $participant) {
                if (is_string($participant)) {
                    $name = trim($participant);
                    $role = null;
                } else {
                    $name = trim((string) ($participant['name'] ?? ''));
                    $role = isset($participant['role']) ? (string) $participant['role'] : null;
                }

                if ($name === '') {
                    continue;
                }

                $row = ['name' => $name];
                $matched = $this->entities->findByAlias($chronicle, $name);
                if ($matched !== null) {
                    $row['matched_entity_id'] = (int) $matched->id;
                }

                if ($role !== null && WorldEventParticipantRole::tryFrom($role) !== null) {
                    $row['role'] = $role;
                }

                $participants[] = $row;
            }

            $allowedIds = array_flip($sceneMessageIds);
            $sourceMessageIds = [];
            foreach ($event['source_message_ids'] ?? [] as $messageId) {
                $id = (int) $messageId;
                if ($id > 0 && isset($allowedIds[$id])) {
                    $sourceMessageIds[] = $id;
                }
            }

            $events[] = [
                'index' => $index,
                'title' => $title,
                'summary' => trim((string) ($event['summary'] ?? '')),
                'participants' => $participants,
                'source_message_ids' => $sourceMessageIds,
                'status' => ExtractionCandidateStatus::Pending->value,
            ];
        }

        foreach ($parsed['memories'] as $index => $memory) {
            if ($sourceType === ExtractionSourceType::Biography) {
                $text = trim((string) ($memory['text'] ?? ''));
                if ($text === '') {
                    continue;
                }

                $memories[] = [
                    'index' => $index,
                    'text' => $text,
                    'node_type' => $this->normalizeMemoryType((string) ($memory['node_type'] ?? '')),
                    'status' => ExtractionCandidateStatus::Pending->value,
                ];

                continue;
            }

            $discarded[] = [
                'index' => $index,
                'type' => 'memory',
                'status' => ExtractionCandidateStatus::Discarded->value,
                'reason' => 'unsupported_for_source',
                'payload' => $memory,
            ];
        }

        return [
            'mentions' => $mentions,
            'relations' => $relations,
            'events' => $events,
            'memories' => $memories,
            'discarded' => $discarded,
        ];
    }

    private function normalizeMemoryType(string $type): string
    {
        $type = strtolower(trim($type));

        $aliases = [
            'fact' => CharacterMemoryNodeType::Conclusion->value,
            'belief' => CharacterMemoryNodeType::Conclusion->value,
        ];

        $type = $aliases[$type] ?? $type;

        return CharacterMemoryNodeType::tryFrom($type)?->value
            ?? CharacterMemoryNodeType::Event->value;
    }

    private function normalizeKind(string $kind): string
    {
        $kind = strtolower(trim($kind));

        foreach (WorldEntityType::cases() as $type) {
            if ($type->value === $kind) {
                return $type->value;
            }
        }

        return $kind;
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @return array<string, mixed>
     */
    public function rematchMention(array $candidate, Chronicle $chronicle): array
    {
        if (array_key_exists('alias_of_entity_id', $candidate) && $candidate['alias_of_entity_id'] !== null) {
            unset($candidate['matched_entity_id'], $candidate['candidate_type']);

            return $candidate;
        }

        unset($candidate['alias_of_entity_id']);

        $matched = $this->entities->findByAlias($chronicle, (string) ($candidate['name'] ?? ''));
        if ($matched !== null) {
            $candidate['matched_entity_id'] = (int) $matched->id;
            unset($candidate['candidate_type']);
        } else {
            $candidate['candidate_type'] = 'new_entity';
            unset($candidate['matched_entity_id']);
        }

        return $candidate;
    }
}
