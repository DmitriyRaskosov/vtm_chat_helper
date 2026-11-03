<?php

namespace App\Context\Providers;

use App\Context\ContextAssembly;
use App\Context\ContextSection;
use App\Context\TokenEstimator;
use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\SceneParticipant;

class InventoryProvider implements ContextProvider
{
    public function __construct(
        private TokenEstimator $estimator,
    ) {}

    public function key(): string
    {
        return 'inventory';
    }

    public function assemble(ContextAssembly $assembly, int $tokenBudget): ContextSection
    {
        $character = $assembly->character;
        if ($character === null) {
            return ContextSection::omitted($this->key(), ['reason' => 'no_character']);
        }

        $items = $character->items()->orderBy('id')->get();
        $equipped = [];
        $carried = [];
        foreach ($items as $item) {
            $label = $this->label($item);
            if ($item->is_equipped) {
                $equipped[] = $label;
            } else {
                $carried[] = $label;
            }
        }

        $others = $this->otherEquippedLines($assembly, $character);
        if ($equipped === [] && $carried === [] && $others === []) {
            return ContextSection::omitted($this->key(), [
                'character_id' => (int) $character->id,
                'reason' => 'empty',
            ]);
        }

        $truncated = false;
        while (true) {
            $content = $this->format($equipped, $carried, $others);
            if ($tokenBudget >= 1 && $this->estimator->estimate($content) <= $tokenBudget) {
                return ContextSection::fromContent($this->key(), $content, $this->estimator, [
                    'character_id' => (int) $character->id,
                    'item_ids' => $items->pluck('id')->map(fn ($id): int => (int) $id)->all(),
                ], $truncated ? 'items_tail' : null);
            }

            $truncated = true;
            if ($others !== []) {
                array_pop($others);

                continue;
            }
            if ($carried !== []) {
                array_pop($carried);

                continue;
            }
            if ($equipped !== []) {
                array_pop($equipped);

                continue;
            }

            break;
        }

        return ContextSection::omitted($this->key(), [
            'character_id' => (int) $character->id,
            'reason' => 'budget',
        ]);
    }

    /**
     * @return list<string>
     */
    private function otherEquippedLines(ContextAssembly $assembly, Character $character): array
    {
        $participants = $assembly->scene->participants()
            ->where('is_current', true)
            ->where('character_id', '!=', $character->id)
            ->with([
                'character.entity',
                'character.items',
            ])
            ->orderBy('id')
            ->get();

        $lines = [];
        foreach ($participants as $participant) {
            $line = $this->otherEquippedLine($participant);
            if ($line !== null) {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    private function otherEquippedLine(SceneParticipant $participant): ?string
    {
        $other = $participant->character;
        if ($other === null) {
            return null;
        }

        $equipped = $other->items
            ->filter(fn (CharacterItem $item): bool => $item->is_equipped)
            ->sortBy('id')
            ->values();
        if ($equipped->isEmpty()) {
            return null;
        }

        $name = trim((string) ($other->entity->canonical_name ?? ''));
        if ($name === '') {
            $name = 'Unknown';
        }

        $labels = $equipped
            ->map(fn (CharacterItem $item): string => $this->label($item))
            ->implode(', ');

        return '- '.$name.': '.$labels;
    }

    private function label(CharacterItem $item): string
    {
        return trim($item->name).' x'.(int) $item->quantity;
    }

    /**
     * @param  list<string>  $equipped
     * @param  list<string>  $carried
     * @param  list<string>  $others
     */
    private function format(array $equipped, array $carried, array $others): string
    {
        $lines = [
            $equipped === [] ? 'Your Equipped:' : 'Your Equipped: '.implode(', ', $equipped),
            $carried === [] ? 'Your Carried:' : 'Your Carried: '.implode(', ', $carried),
        ];

        if ($others !== []) {
            $lines[] = "Others' Equipped:";
            foreach ($others as $line) {
                $lines[] = $line;
            }
        }

        return implode("\n", $lines);
    }
}
