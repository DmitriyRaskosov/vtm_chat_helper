<?php

namespace App\World;

use App\Enums\WorldEntityType;

final class WorldRelationTypeCatalog
{
    /**
     * Seeded relation types. New types are extra rows, not a schema migration
     * of the (future) world_relations table.
     *
     * @return list<array{
     *     key: string,
     *     display_name: string,
     *     allowed_source_types: list<string>,
     *     allowed_target_types: list<string>,
     *     symmetric: bool,
     *     transitive: bool,
     *     default_weight: float,
     *     enabled: bool,
     *     inverse_key: string|null
     * }>
     */
    public static function definitions(): array
    {
        return [
            self::row('knows', 'Knows', [WorldEntityType::Character], WorldEntityType::cases(), false, false),
            self::row(
                'member_of',
                'Member of',
                [
                    WorldEntityType::Character,
                    WorldEntityType::Clan,
                    WorldEntityType::Coterie,
                    WorldEntityType::Circle,
                    WorldEntityType::Faction,
                ],
                [WorldEntityType::Faction],
                false,
                false,
            ),
            self::row(
                'located_at',
                'Located at',
                [WorldEntityType::Character, WorldEntityType::Item, WorldEntityType::Event],
                [WorldEntityType::Location],
                false,
                false,
            ),
            self::row(
                'owns',
                'Owns',
                [WorldEntityType::Character, WorldEntityType::Faction],
                [WorldEntityType::Item],
                false,
                false,
            ),
            self::row(
                'controls',
                'Controls',
                [WorldEntityType::Character, WorldEntityType::Faction],
                [WorldEntityType::Location, WorldEntityType::Faction],
                false,
                false,
            ),
            self::row(
                'allied_with',
                'Allied with',
                [WorldEntityType::Character, WorldEntityType::Faction],
                [WorldEntityType::Character, WorldEntityType::Faction],
                true,
                false,
            ),
            self::row(
                'hostile_to',
                'Hostile to',
                [WorldEntityType::Character, WorldEntityType::Faction],
                [WorldEntityType::Character, WorldEntityType::Faction],
                true,
                false,
            ),
            self::row(
                'created',
                'Created',
                [WorldEntityType::Character],
                [WorldEntityType::Item, WorldEntityType::Concept, WorldEntityType::Event, WorldEntityType::Faction],
                false,
                false,
            ),
            self::row('participated_in', 'Participated in', [WorldEntityType::Character], [WorldEntityType::Event], false, false),
            self::row(
                'caused',
                'Caused',
                [WorldEntityType::Character, WorldEntityType::Faction, WorldEntityType::Event],
                [WorldEntityType::Event],
                false,
                false,
            ),
            self::row('witnessed', 'Witnessed', [WorldEntityType::Character], [WorldEntityType::Event], false, false),
            self::row(
                'affiliated_with',
                'Affiliated with',
                [WorldEntityType::Character],
                [
                    WorldEntityType::Faction,
                    WorldEntityType::Clan,
                    WorldEntityType::Coterie,
                    WorldEntityType::Circle,
                    WorldEntityType::Other,
                    WorldEntityType::Location,
                    WorldEntityType::Item,
                    WorldEntityType::Concept,
                ],
                false,
                false,
            ),
            self::row('occurred_at', 'Occurred at', [WorldEntityType::Event], [WorldEntityType::Location], false, false),
            self::row(
                'part_of',
                'Part of',
                [WorldEntityType::Concept],
                [
                    WorldEntityType::Concept,
                    WorldEntityType::Faction,
                    WorldEntityType::Clan,
                    WorldEntityType::Coterie,
                    WorldEntityType::Circle,
                    WorldEntityType::Other,
                    WorldEntityType::Location,
                    WorldEntityType::Item,
                ],
                false,
                true,
                inverseKey: 'contains',
            ),
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_column(self::definitions(), 'key');
    }

    /**
     * @param  list<WorldEntityType>  $sources
     * @param  list<WorldEntityType>  $targets
     * @return array{
     *     key: string,
     *     display_name: string,
     *     allowed_source_types: list<string>,
     *     allowed_target_types: list<string>,
     *     symmetric: bool,
     *     transitive: bool,
     *     default_weight: float,
     *     enabled: bool,
     *     inverse_key: string|null
     * }
     */
    private static function row(
        string $key,
        string $displayName,
        array $sources,
        array $targets,
        bool $symmetric,
        bool $transitive,
        float $defaultWeight = 1.0,
        ?string $inverseKey = null,
    ): array {
        return [
            'key' => $key,
            'display_name' => $displayName,
            'allowed_source_types' => array_map(fn (WorldEntityType $type): string => $type->value, $sources),
            'allowed_target_types' => array_map(fn (WorldEntityType $type): string => $type->value, $targets),
            'symmetric' => $symmetric,
            'transitive' => $transitive,
            'default_weight' => $defaultWeight,
            'enabled' => true,
            'inverse_key' => $inverseKey,
        ];
    }
}
