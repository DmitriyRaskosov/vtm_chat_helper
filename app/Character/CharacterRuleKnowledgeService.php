<?php

namespace App\Character;

use App\Enums\CharacterKnowledgeLevel;
use App\Models\Character;
use App\Models\CharacterRuleKnowledge;
use App\Models\RuleDocument;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CharacterRuleKnowledgeService
{
    public function grant(
        Character $character,
        RuleDocument $document,
        CharacterKnowledgeLevel $level = CharacterKnowledgeLevel::Known,
        int $confidence = 3,
        ?User $approver = null,
        mixed $learnedAt = null,
    ): CharacterRuleKnowledge {
        if ($confidence < 0 || $confidence > 5) {
            throw new InvalidArgumentException('Knowledge confidence must be between 0 and 5.');
        }

        return DB::transaction(function () use ($character, $document, $level, $confidence, $approver, $learnedAt): CharacterRuleKnowledge {
            $existing = CharacterRuleKnowledge::query()
                ->where('character_id', $character->id)
                ->where('rule_document_id', $document->id)
                ->lockForUpdate()
                ->first();

            $payload = [
                'knowledge_level' => $level,
                'confidence' => $confidence,
                'learned_at' => $learnedAt ?? now(),
                'approved_at' => $approver !== null ? now() : $existing?->approved_at,
                'approved_by' => $approver?->id ?? $existing?->approved_by,
            ];

            if ($existing !== null) {
                $existing->fill($payload);
                $existing->save();

                return $existing->refresh();
            }

            return CharacterRuleKnowledge::query()->create([
                'character_id' => $character->id,
                'rule_document_id' => $document->id,
                ...$payload,
            ]);
        });
    }

    /**
     * @return list<int>
     */
    public function knownRuleDocumentIds(Character $character): array
    {
        return CharacterRuleKnowledge::query()
            ->where('character_id', $character->id)
            ->pluck('rule_document_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }
}
