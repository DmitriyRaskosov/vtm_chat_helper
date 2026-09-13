<?php

namespace App\Memory;

use App\Enums\CharacterMemoryEdgeType;
use App\Enums\CharacterMemoryNodeType;
use App\Enums\CharacterMemoryStatus;
use App\Models\Character;
use App\Models\CharacterMemoryEdge;
use App\Models\CharacterMemoryNode;
use App\Models\User;
use App\Rag\EmbeddingProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CharacterMemoryService
{
    public function __construct(private EmbeddingProvider $embeddings) {}

    /**
     * @param  list<string>  $aliases
     * @param  array<string, mixed>|null  $provenance
     */
    public function remember(
        Character $character,
        string $nodeText,
        CharacterMemoryNodeType $type,
        int $importance = 1,
        int $emotionalValence = 0,
        int $arousal = 0,
        int $confidence = 3,
        bool $isFalseBelief = false,
        array $aliases = [],
        CharacterMemoryStatus $status = CharacterMemoryStatus::Approved,
        ?User $author = null,
        ?array $provenance = null,
    ): CharacterMemoryNode {
        $nodeText = trim($nodeText);

        if ($nodeText === '') {
            throw new InvalidArgumentException('Memory node text is required.');
        }

        $this->assertRange('importance', $importance, 0, 5);
        $this->assertRange('emotional_valence', $emotionalValence, -5, 5);
        $this->assertRange('arousal', $arousal, 0, 5);
        $this->assertRange('confidence', $confidence, 0, 5);

        $aliases = array_values(array_filter(array_map(
            fn (string $alias): string => trim($alias),
            $aliases,
        ), fn (string $alias): bool => $alias !== ''));

        return CharacterMemoryNode::query()->create([
            'character_id' => $character->id,
            'node_text' => $nodeText,
            'node_type' => $type,
            'importance' => $importance,
            'emotional_valence' => $emotionalValence,
            'arousal' => $arousal,
            'confidence' => $confidence,
            'is_false_belief' => $isFalseBelief,
            'recall_count' => 0,
            'last_recalled_at' => null,
            'last_recall_score' => null,
            'status' => $status,
            'aliases' => $aliases,
            'provenance' => $provenance ?? [],
            'approved_at' => $status === CharacterMemoryStatus::Approved ? now() : null,
            'approved_by' => $status === CharacterMemoryStatus::Approved ? $author?->id : null,
            'created_by' => $author?->id,
            'embedding' => $this->embeddings->embed($nodeText),
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $provenance
     */
    public function associate(
        CharacterMemoryNode $source,
        CharacterMemoryNode $target,
        CharacterMemoryEdgeType $type,
        float $authoredWeight = 1.0,
        bool $bidirectional = false,
        ?User $approver = null,
        ?array $provenance = null,
    ): CharacterMemoryEdge {
        if ((int) $source->id === (int) $target->id) {
            throw new CharacterMemoryException('A memory edge cannot be a self-loop.');
        }

        if ((int) $source->character_id !== (int) $target->character_id) {
            throw new CharacterMemoryException('Memory edges must connect nodes of the same character.');
        }

        if ($authoredWeight < -1 || $authoredWeight > 1) {
            throw new CharacterMemoryException('Authored weight must be between -1 and 1.');
        }

        return DB::transaction(function () use ($source, $target, $type, $authoredWeight, $bidirectional, $approver, $provenance): CharacterMemoryEdge {
            $directed = CharacterMemoryEdge::query()
                ->where('character_id', $source->character_id)
                ->where('relation_type', $type)
                ->where('source_node_id', $source->id)
                ->where('target_node_id', $target->id)
                ->lockForUpdate()
                ->exists();

            $reverse = CharacterMemoryEdge::query()
                ->where('character_id', $source->character_id)
                ->where('relation_type', $type)
                ->where('source_node_id', $target->id)
                ->where('target_node_id', $source->id)
                ->lockForUpdate()
                ->first();

            if ($directed || ($reverse !== null && ($bidirectional || $reverse->bidirectional))) {
                throw new CharacterMemoryException('A memory edge of this type already exists between these nodes.');
            }

            return CharacterMemoryEdge::query()->create([
                'character_id' => $source->character_id,
                'source_node_id' => $source->id,
                'target_node_id' => $target->id,
                'relation_type' => $type,
                'authored_weight' => $authoredWeight,
                'bidirectional' => $bidirectional,
                'traversal_count' => 0,
                'last_traversed_at' => null,
                'provenance' => $provenance ?? [],
                'approved_at' => $approver !== null ? now() : null,
                'approved_by' => $approver?->id,
            ]);
        });
    }

    /**
     * Directed outgoing edges plus incoming edges marked bidirectional.
     * Does not update traversal metrics.
     *
     * @return Collection<int, CharacterMemoryEdge>
     */
    public function neighbors(CharacterMemoryNode $node, ?CharacterMemoryEdgeType $type = null): Collection
    {
        $outgoing = CharacterMemoryEdge::query()
            ->where('source_node_id', $node->id)
            ->when($type !== null, fn ($query) => $query->where('relation_type', $type))
            ->with(['source', 'target'])
            ->get();

        $incoming = CharacterMemoryEdge::query()
            ->where('target_node_id', $node->id)
            ->where('bidirectional', true)
            ->when($type !== null, fn ($query) => $query->where('relation_type', $type))
            ->with(['source', 'target'])
            ->get();

        return $outgoing->concat($incoming)->unique('id')->values();
    }

    private function assertRange(string $field, int $value, int $min, int $max): void
    {
        if ($value < $min || $value > $max) {
            throw new InvalidArgumentException("{$field} must be between {$min} and {$max}.");
        }
    }
}
