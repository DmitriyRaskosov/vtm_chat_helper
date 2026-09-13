<?php

namespace App\Character;

use App\Enums\CharacterKnowledgeLevel;
use App\Enums\LoreEntryStatus;
use App\Enums\LoreKnowledgeAccess;
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
        return $this->upsertException(
            $character,
            $entry,
            LoreKnowledgeAccess::Grant,
            $level,
            $confidence,
            $sourceEvent,
            $approver,
            $learnedAt,
        );
    }

    public function deny(
        Character $character,
        LoreEntry $entry,
        ?User $approver = null,
    ): CharacterLoreKnowledge {
        return $this->upsertException(
            $character,
            $entry,
            LoreKnowledgeAccess::Deny,
            CharacterKnowledgeLevel::Known,
            3,
            null,
            $approver,
            now(),
        );
    }

    public function revoke(Character $character, LoreEntry $entry): void
    {
        CharacterLoreKnowledge::query()
            ->where('character_id', $character->id)
            ->where('lore_entry_id', $entry->id)
            ->delete();
    }

    /**
     * @param  list<int>  $grantedCharacterIds
     * @param  list<int>  $deniedCharacterIds
     */
    public function syncExceptions(
        LoreEntry $entry,
        array $grantedCharacterIds,
        array $deniedCharacterIds = [],
        ?User $approver = null,
    ): void {
        $granted = array_values(array_unique(array_map('intval', $grantedCharacterIds)));
        $denied = array_values(array_unique(array_map('intval', $deniedCharacterIds)));

        if (array_intersect($granted, $denied) !== []) {
            throw new InvalidArgumentException('A character cannot be both granted and denied the same lore entry.');
        }

        $keep = array_values(array_unique([...$granted, ...$denied]));

        DB::transaction(function () use ($entry, $granted, $denied, $keep, $approver): void {
            $current = CharacterLoreKnowledge::query()
                ->where('lore_entry_id', $entry->id)
                ->lockForUpdate()
                ->get();

            foreach ($current as $row) {
                if (! in_array((int) $row->character_id, $keep, true)) {
                    $row->delete();
                }
            }

            foreach ($granted as $id) {
                $this->grant(
                    Character::query()->findOrFail($id),
                    $entry,
                    CharacterKnowledgeLevel::Known,
                    3,
                    approver: $approver,
                );
            }

            foreach ($denied as $id) {
                $this->deny(Character::query()->findOrFail($id), $entry, $approver);
            }
        });
    }

    /**
     * @param  list<int>  $characterIds
     */
    public function syncForEntry(LoreEntry $entry, array $characterIds, ?User $approver = null): void
    {
        $this->syncExceptions($entry, $characterIds, [], $approver);
    }

    /**
     * Grant-exception character IDs (not everyone who can see the article).
     *
     * @return list<int>
     */
    public function grantedCharacterIds(LoreEntry $entry): array
    {
        return $this->exceptionCharacterIds($entry, LoreKnowledgeAccess::Grant);
    }

    /**
     * @return list<int>
     */
    public function deniedCharacterIds(LoreEntry $entry): array
    {
        return $this->exceptionCharacterIds($entry, LoreKnowledgeAccess::Deny);
    }

    /**
     * @deprecated Use grantedCharacterIds()
     *
     * @return list<int>
     */
    public function knownCharacterIds(LoreEntry $entry): array
    {
        return $this->grantedCharacterIds($entry);
    }

    /**
     * Grant-exception lore IDs for this character (not the full visible set).
     *
     * @return list<int>
     */
    public function knownLoreEntryIds(Character $character): array
    {
        return $this->exceptionEntryIds($character, LoreKnowledgeAccess::Grant);
    }

    /**
     * Approved lore this NPC may see: clearance >= classification, plus grants, minus denials.
     *
     * @return list<int>
     */
    public function visibleLoreEntryIds(Character $character): array
    {
        $levels = $this->clearanceLevelValues($character);
        $granted = $this->exceptionEntryIds($character, LoreKnowledgeAccess::Grant);
        $denied = $this->exceptionEntryIds($character, LoreKnowledgeAccess::Deny);

        return LoreEntry::query()
            ->where('chronicle_id', $character->chronicle_id)
            ->where('status', '!=', LoreEntryStatus::Archived)
            ->where(function ($query) use ($levels, $granted): void {
                $query->where(function ($byLevel) use ($levels): void {
                    $byLevel->where('situational', false)
                        ->whereIn('classification', $levels);
                });
                if ($granted !== []) {
                    $query->orWhereIn('id', $granted);
                }
            })
            ->when($denied !== [], fn ($query) => $query->whereNotIn('id', $denied))
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @return list<string>
     */
    private function clearanceLevelValues(Character $character): array
    {
        $levels = $character->lore_clearance_levels;
        if (! is_array($levels) || $levels === []) {
            return [];
        }

        return array_values(array_map(
            fn (mixed $level): string => (string) (int) $level,
            $levels,
        ));
    }

    private function upsertException(
        Character $character,
        LoreEntry $entry,
        LoreKnowledgeAccess $access,
        CharacterKnowledgeLevel $level,
        int $confidence,
        ?WorldEvent $sourceEvent,
        ?User $approver,
        mixed $learnedAt,
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

        return DB::transaction(function () use ($character, $entry, $access, $level, $confidence, $sourceEvent, $approver, $learnedAt): CharacterLoreKnowledge {
            $existing = CharacterLoreKnowledge::query()
                ->where('character_id', $character->id)
                ->where('lore_entry_id', $entry->id)
                ->lockForUpdate()
                ->first();

            $payload = [
                'knowledge_level' => $level,
                'access' => $access,
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
    private function exceptionCharacterIds(LoreEntry $entry, LoreKnowledgeAccess $access): array
    {
        return CharacterLoreKnowledge::query()
            ->where('lore_entry_id', $entry->id)
            ->where('access', $access)
            ->orderBy('character_id')
            ->pluck('character_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @return list<int>
     */
    private function exceptionEntryIds(Character $character, LoreKnowledgeAccess $access): array
    {
        return CharacterLoreKnowledge::query()
            ->where('character_id', $character->id)
            ->where('access', $access)
            ->pluck('lore_entry_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }
}
