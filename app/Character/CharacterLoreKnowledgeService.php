<?php

namespace App\Character;

use App\Enums\CharacterKnowledgeLevel;
use App\Models\Character;
use App\Models\CharacterLoreKnowledge;
use App\Models\LoreEntry;
use App\Models\User;
use App\Models\WorldEvent;
use App\World\MixedChronicleException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CharacterLoreKnowledgeService
{
    public function grant(
        Character $character,
        LoreEntry $entry,
        CharacterKnowledgeLevel $level = CharacterKnowledgeLevel::Known,
        int $confidence = 3,
        ?WorldEvent $sourceEvent = null,
        ?User $approver = null,
        mixed $learnedAt = null,
    ): CharacterLoreKnowledge {
        if ((int) $character->chronicle_id !== (int) $entry->chronicle_id) {
            throw new MixedChronicleException;
        }

        if ($confidence < 0 || $confidence > 5) {
            throw new InvalidArgumentException('Knowledge confidence must be between 0 and 5.');
        }

        if ($sourceEvent !== null && (int) $sourceEvent->chronicle_id !== (int) $character->chronicle_id) {
            throw new MixedChronicleException;
        }

        return DB::transaction(function () use ($character, $entry, $level, $confidence, $sourceEvent, $approver, $learnedAt): CharacterLoreKnowledge {
            $existing = CharacterLoreKnowledge::query()
                ->where('character_id', $character->id)
                ->where('lore_entry_id', $entry->id)
                ->lockForUpdate()
                ->first();

            $payload = [
                'knowledge_level' => $level,
                'confidence' => $confidence,
                'learned_at' => $learnedAt ?? now(),
                'source_world_event_id' => $sourceEvent?->id,
                'approved_at' => $approver !== null ? now() : $existing?->approved_at,
                'approved_by' => $approver?->id ?? $existing?->approved_by,
            ];

            if ($existing !== null) {
                $existing->fill($payload);
                $existing->save();

                return $existing->refresh();
            }

            return CharacterLoreKnowledge::query()->create([
                'character_id' => $character->id,
                'lore_entry_id' => $entry->id,
                'chronicle_id' => $character->chronicle_id,
                ...$payload,
            ]);
        });
    }

    /**
     * @return list<int>
     */
    public function knownLoreEntryIds(Character $character): array
    {
        return CharacterLoreKnowledge::query()
            ->where('character_id', $character->id)
            ->pluck('lore_entry_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }
}
