<?php

namespace Tests\Support;

use App\Models\ChronicleLocation;
use App\Models\LocationNode;
use App\Rag\VectorLiteral;
use Illuminate\Support\Facades\DB;

trait CreatesLocationNodes
{
    /**
     * @param  list<float>  $embedding
     */
    protected function createLocationNode(
        ChronicleLocation $location,
        string $name,
        ?string $description,
        ?int $parentId,
        array $embedding,
    ): LocationNode {
        $id = (int) DB::table('location_nodes')->insertGetId([
            'chronicle_location_id' => $location->id,
            'parent_id' => $parentId,
            'name' => $name,
            'description' => $description,
            'embedding' => DB::raw("'".VectorLiteral::fromArray($embedding)."'::vector"),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return LocationNode::query()->findOrFail($id);
    }
}
