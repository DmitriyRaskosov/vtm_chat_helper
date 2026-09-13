<?php

namespace App\Character;

use App\Models\Character;
use App\Models\CharacterRelationship;
use App\Models\WorldEntity;
use App\Models\WorldRelationType;
use App\World\WorldRelationService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CharacterRelationshipService
{
    public function __construct(private WorldRelationService $relations) {}

    /**
     * @param  array<string, mixed>|null  $provenance
     */
    public function connect(
        Character $source,
        Character $target,
        WorldRelationType $type,
        ?float $weight = null,
        ?string $note = null,
        ?array $provenance = null,
    ): CharacterRelationship {
        if ((int) $source->id === (int) $target->id) {
            throw new InvalidArgumentException('A character cannot have a relationship with themselves.');
        }

        return DB::transaction(function () use ($source, $target, $type, $weight, $note, $provenance): CharacterRelationship {
            $sourceEntity = WorldEntity::query()->findOrFail($source->id);
            $targetEntity = WorldEntity::query()->findOrFail($target->id);

            $edge = $this->relations->relate(
                $sourceEntity,
                $targetEntity,
                $type,
                $weight,
                $note,
                $provenance,
            );

            return CharacterRelationship::query()->create([
                'id' => $edge->id,
                'chronicle_id' => $source->chronicle_id,
                'source_character_id' => $source->id,
                'target_character_id' => $target->id,
            ])->refresh();
        });
    }
}
