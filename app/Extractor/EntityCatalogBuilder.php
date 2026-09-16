<?php

namespace App\Extractor;

use App\Enums\WorldEntityStatus;
use App\Models\Chronicle;
use App\Models\WorldEntity;
use App\World\AliasNormalizer;

class EntityCatalogBuilder
{
    public function __construct(private ExtractorTokenBudget $tokenBudget) {}

    /**
     * @param  list<int>  $alwaysIncludeEntityIds
     * @return list<string>
     */
    public function build(
        Chronicle $chronicle,
        ?string $sliceText = null,
        array $alwaysIncludeEntityIds = [],
        string $profile = 'lore',
    ): array {
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

        $priorityLines = [];
        $matchedLines = [];

        foreach ($entities as $entity) {
            if ($filterBySlice && ! $this->entityBelongsInCatalog($entity, $haystack, $sliceText, $alwaysInclude)) {
                continue;
            }

            $line = $this->formatLine($entity);

            if (isset($alwaysInclude[(int) $entity->id])) {
                $priorityLines[] = $line;

                continue;
            }

            $matchedLines[] = $line;

            if (count($priorityLines) + count($matchedLines) >= $this->maxLines()) {
                break;
            }
        }

        return $this->trimLinesToTokenBudget(
            [...$priorityLines, ...$matchedLines],
            $this->tokenBudget->profileCatalogTokenLimit($profile),
        );
    }

    private function maxLines(): int
    {
        return max(1, (int) config('extractor.catalog_max_entities'));
    }

    /**
     * @param  array<int, true>  $alwaysInclude
     */
    private function entityBelongsInCatalog(
        WorldEntity $entity,
        ?string $haystack,
        ?string $originalSlice,
        array $alwaysInclude,
    ): bool {
        if (isset($alwaysInclude[(int) $entity->id])) {
            return true;
        }

        if ($haystack === null || $haystack === '') {
            return false;
        }

        if ($this->nameAppearsInSlice($entity->canonical_name, $haystack, $originalSlice)) {
            return true;
        }

        foreach ($entity->aliases as $alias) {
            if ($this->nameAppearsInSlice($alias->alias, $haystack, $originalSlice)) {
                return true;
            }
        }

        return false;
    }

    private function nameAppearsInSlice(string $name, string $haystack, ?string $originalSlice = null): bool
    {
        $normalized = AliasNormalizer::normalize($name);
        if ($normalized === '') {
            return false;
        }

        $needle = mb_strtolower($normalized);
        $pattern = '/(?<![\p{L}\p{N}])'
            .preg_quote($needle, '/')
            .'(?![\p{L}\p{N}])/u';

        if (preg_match($pattern, $haystack) !== 1) {
            return false;
        }

        if ($originalSlice === null) {
            return true;
        }

        $longerPattern = '/\p{Lu}\p{L}*[\s\-]+'
            .preg_quote($needle, '/')
            .'(?![\p{L}\p{N}])/ui';

        return preg_match($longerPattern, $originalSlice) !== 1;
    }

    /**
     * @param  list<string>  $lines
     * @return list<string>
     */
    private function trimLinesToTokenBudget(array $lines, int $maxTokens): array
    {
        if ($lines === []) {
            return [];
        }

        $result = [];
        $usedTokens = 0;

        foreach ($lines as $line) {
            $lineTokens = $this->tokenBudget->estimateTokens($line."\n");

            if ($result !== [] && ($usedTokens + $lineTokens) > $maxTokens) {
                break;
            }

            $result[] = $line;
            $usedTokens += $lineTokens;
        }

        return $result;
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
