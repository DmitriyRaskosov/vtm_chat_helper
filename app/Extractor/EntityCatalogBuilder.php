<?php

namespace App\Extractor;

use App\Enums\WorldEntityStatus;
use App\Models\Chronicle;
use App\Models\WorldEntity;
use App\World\AliasNormalizer;

class EntityCatalogBuilder
{
    /**
     * @param  list<int>  $alwaysIncludeEntityIds
     * @return list<string>
     */
    public function build(Chronicle $chronicle, ?string $sliceText = null, array $alwaysIncludeEntityIds = []): array
    {
        $entities = WorldEntity::query()
            ->where('chronicle_id', $chronicle->id)
            ->where('status', WorldEntityStatus::Active)
            ->with('aliases')
            ->orderBy('entity_type')
            ->orderBy('canonical_name')
            ->orderBy('id')
            ->get();

        $alwaysInclude = array_fill_keys(array_map('intval', $alwaysIncludeEntityIds), true);
        $haystack = $sliceText === null ? null : mb_strtolower($sliceText);
        $filterBySlice = $haystack !== null || $alwaysInclude !== [];

        $lines = [];
        foreach ($entities as $entity) {
            if ($filterBySlice && ! $this->entityBelongsInCatalog($entity, $haystack, $alwaysInclude)) {
                continue;
            }

            $lines[] = $this->formatLine($entity);

            if (count($lines) >= $this->maxLines()) {
                break;
            }
        }

        return $lines;
    }

    private function maxLines(): int
    {
        return max(1, (int) config('extractor.catalog_max_entities'));
    }

    /**
     * @param  array<int, true>  $alwaysInclude
     */
    private function entityBelongsInCatalog(WorldEntity $entity, ?string $haystack, array $alwaysInclude): bool
    {
        if (isset($alwaysInclude[(int) $entity->id])) {
            return true;
        }

        if ($haystack === null || $haystack === '') {
            return false;
        }

        if ($this->nameAppearsInSlice($entity->canonical_name, $haystack)) {
            return true;
        }

        foreach ($entity->aliases as $alias) {
            if ($this->nameAppearsInSlice($alias->alias, $haystack)) {
                return true;
            }
        }

        return false;
    }

    private function nameAppearsInSlice(string $name, string $haystack): bool
    {
        $normalized = AliasNormalizer::normalize($name);
        if ($normalized === '') {
            return false;
        }

        return str_contains($haystack, mb_strtolower($normalized));
    }

    private function formatLine(WorldEntity $entity): string
    {
        $aliases = $entity->aliases
            ->pluck('alias')
            ->filter(fn (string $alias): bool => $alias !== $entity->canonical_name)
            ->unique()
            ->values()
            ->all();

        $aliasText = $aliases === [] ? '' : implode(', ', $aliases);

        return sprintf(
            '%d | %s | %s | %s',
            $entity->id,
            $entity->entity_type->value,
            $entity->canonical_name,
            $aliasText,
        );
    }
}
