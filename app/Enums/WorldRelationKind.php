<?php

/**
 * Типы связей между сущностями мира.
 * 
 * Унаследовано от заброшенного проекта (world_graph, ~2025). 
 * Реально не используется: API связей удалён (ADR-002), 
 * DirectRelationsProvider возвращает omitted.
 * При возвращении к графу NPC↔NPC — пересмотреть (сейчас нужна 
 * другая модель: веса, эмоции, время).
 */

namespace App\Enums;

enum WorldRelationKind: string
{
    case Knows = 'knows';
    case Owns = 'owns';
    case Controls = 'controls';
    case AlliedWith = 'allied_with';
    case HostileTo = 'hostile_to';
    case Created = 'created';
    case ParticipatedIn = 'participated_in';
    case Caused = 'caused';
    case Witnessed = 'witnessed';
    case AffiliatedWith = 'affiliated_with';
    case OccurredAt = 'occurred_at';
    case PartOf = 'part_of';
    case Contains = 'contains';

    public function isSymmetric(): bool
    {
        return match ($this) {
            self::AlliedWith, self::HostileTo => true,
            default => false,
        };
    }

    public function hasInverse(): bool
    {
        return match ($this) {
            self::PartOf, self::Contains => true,
            default => false,
        };
    }

    /**
     * Relation keys stored on incoming edges that should appear in neighbors().
     *
     * @return list<self>
     */
    public static function incomingEdgeKinds(): array
    {
        $kinds = [];
        foreach (self::cases() as $case) {
            if ($case->isSymmetric() || $case->hasInverse()) {
                $kinds[] = $case;
            }
        }

        return $kinds;
    }
}
