<?php

namespace App\Character;

use App\Models\CanonClan;
use App\Models\CanonDiscipline;
use App\Models\CanonSect;

final class CharacterCatalog
{
    public function load(): CharacterCatalogData
    {
        $disciplines = CanonDiscipline::query()
            ->orderBy('name')
            ->get(['id', 'slug', 'name', 'description', 'is_common'])
            ->map(fn (CanonDiscipline $row): CharacterCatalogDiscipline => new CharacterCatalogDiscipline(
                id: (int) $row->id,
                slug: $row->slug,
                name: $row->name,
                description: $row->description,
                isCommon: (bool) $row->is_common,
            ))
            ->values()
            ->all();

        $clans = CanonClan::query()
            ->where('is_playable', true)
            ->where('is_bloodline', false)
            ->orderBy('name')
            ->get(['id', 'slug', 'name', 'nickname'])
            ->map(fn (CanonClan $row): CharacterCatalogClan => new CharacterCatalogClan(
                id: (int) $row->id,
                slug: $row->slug,
                name: $row->name,
                nickname: $row->nickname,
            ))
            ->values()
            ->all();

        $bloodlines = CanonClan::query()
            ->where('is_playable', true)
            ->where('is_bloodline', true)
            ->orderBy('name')
            ->get(['id', 'slug', 'name', 'nickname', 'parent_clan_id'])
            ->map(fn (CanonClan $row): CharacterCatalogBloodline => new CharacterCatalogBloodline(
                id: (int) $row->id,
                slug: $row->slug,
                name: $row->name,
                nickname: $row->nickname,
                parentClanId: $row->parent_clan_id === null ? null : (int) $row->parent_clan_id,
            ))
            ->values()
            ->all();

        $sects = CanonSect::query()
            ->orderBy('name')
            ->get(['id', 'slug', 'name'])
            ->map(fn (CanonSect $row): CharacterCatalogSect => new CharacterCatalogSect(
                id: (int) $row->id,
                slug: $row->slug,
                name: $row->name,
            ))
            ->values()
            ->all();

        return new CharacterCatalogData(
            disciplines: $disciplines,
            clans: $clans,
            bloodlines: $bloodlines,
            sects: $sects,
        );
    }
}

final readonly class CharacterCatalogData
{
    /**
     * @param  list<CharacterCatalogDiscipline>  $disciplines
     * @param  list<CharacterCatalogClan>  $clans
     * @param  list<CharacterCatalogBloodline>  $bloodlines
     * @param  list<CharacterCatalogSect>  $sects
     */
    public function __construct(
        public array $disciplines,
        public array $clans,
        public array $bloodlines,
        public array $sects,
    ) {}

    /**
     * @return array{
     *     disciplines: list<array{id: int, slug: string, name: string, description: ?string, is_common: bool}>,
     *     clans: list<array{id: int, slug: string, name: string, nickname: ?string}>,
     *     bloodlines: list<array{id: int, slug: string, name: string, nickname: ?string, parent_clan_id: ?int}>,
     *     sects: list<array{id: int, slug: string, name: string}>
     * }
     */
    public function toArray(): array
    {
        return [
            'disciplines' => array_map(
                fn (CharacterCatalogDiscipline $row): array => $row->toArray(),
                $this->disciplines,
            ),
            'clans' => array_map(
                fn (CharacterCatalogClan $row): array => $row->toArray(),
                $this->clans,
            ),
            'bloodlines' => array_map(
                fn (CharacterCatalogBloodline $row): array => $row->toArray(),
                $this->bloodlines,
            ),
            'sects' => array_map(
                fn (CharacterCatalogSect $row): array => $row->toArray(),
                $this->sects,
            ),
        ];
    }
}

final readonly class CharacterCatalogDiscipline
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $name,
        public ?string $description,
        public bool $isCommon,
    ) {}

    /**
     * @return array{id: int, slug: string, name: string, description: ?string, is_common: bool}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'is_common' => $this->isCommon,
        ];
    }
}

final readonly class CharacterCatalogClan
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $name,
        public ?string $nickname,
    ) {}

    /**
     * @return array{id: int, slug: string, name: string, nickname: ?string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'nickname' => $this->nickname,
        ];
    }
}

final readonly class CharacterCatalogBloodline
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $name,
        public ?string $nickname,
        public ?int $parentClanId,
    ) {}

    /**
     * @return array{id: int, slug: string, name: string, nickname: ?string, parent_clan_id: ?int}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'nickname' => $this->nickname,
            'parent_clan_id' => $this->parentClanId,
        ];
    }
}

final readonly class CharacterCatalogSect
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $name,
    ) {}

    /**
     * @return array{id: int, slug: string, name: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
        ];
    }
}
