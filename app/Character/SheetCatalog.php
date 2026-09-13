<?php

namespace App\Character;

final class SheetCatalog
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return config('character_sheet');
    }

    /**
     * @return list<array{key: string, display_name: string, sort_order: int, category: string, group: string, maximum: int|null}>
     */
    public function traits(): array
    {
        $rows = [];

        foreach (['physical' => 'attribute', 'social' => 'attribute', 'mental' => 'attribute'] as $group => $category) {
            foreach (config('character_sheet.attributes.'.$group, []) as $trait) {
                $rows[] = $this->row($trait, $category, $group);
            }
        }

        foreach (['talents' => 'ability', 'skills' => 'ability', 'knowledges' => 'ability'] as $group => $category) {
            foreach (config('character_sheet.abilities.'.$group, []) as $trait) {
                $rows[] = $this->row($trait, $category, $group);
            }
        }

        foreach (config('character_sheet.backgrounds', []) as $trait) {
            $rows[] = $this->row($trait, 'background', 'backgrounds');
        }

        foreach (config('character_sheet.virtues', []) as $trait) {
            $rows[] = $this->row($trait, 'virtue', 'virtues');
        }

        foreach (config('character_sheet.other', []) as $trait) {
            $rows[] = $this->row($trait, 'other', 'other');
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $trait
     * @return array{key: string, display_name: string, sort_order: int, category: string, group: string, maximum: int|null}
     */
    private function row(array $trait, string $category, string $group): array
    {
        return [
            'key' => (string) $trait['key'],
            'display_name' => (string) $trait['display_name'],
            'sort_order' => (int) $trait['sort_order'],
            'category' => $category,
            'group' => $group,
            'maximum' => isset($trait['maximum']) ? (int) $trait['maximum'] : 5,
        ];
    }
}
