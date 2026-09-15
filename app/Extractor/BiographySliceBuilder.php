<?php

namespace App\Extractor;

use App\Models\CharacterBiography;

class BiographySliceBuilder
{
    /**
     * @var list<array{label: string, field: string}>
     */
    private const SECTIONS = [
        ['label' => 'Summary', 'field' => 'summary'],
        ['label' => 'Full text', 'field' => 'full_text'],
        ['label' => 'Principles', 'field' => 'principles'],
        ['label' => 'Motivation', 'field' => 'motivation'],
        ['label' => 'Fears', 'field' => 'fears'],
        ['label' => 'Desires', 'field' => 'desires'],
        ['label' => 'Behavioral rules', 'field' => 'behavioral_rules'],
    ];

    public function build(CharacterBiography $biography): string
    {
        $parts = [];

        foreach (self::SECTIONS as $section) {
            $value = trim((string) ($biography->{$section['field']} ?? ''));
            if ($value === '') {
                continue;
            }

            $parts[] = "## {$section['label']}\n{$value}";
        }

        return implode("\n\n", $parts);
    }

    public function isEmpty(CharacterBiography $biography): bool
    {
        return $this->build($biography) === '';
    }
}
