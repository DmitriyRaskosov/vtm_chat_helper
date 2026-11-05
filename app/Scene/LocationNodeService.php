<?php

namespace App\Scene;

use App\Models\ChronicleLocation;
use App\Models\LocationNode;
use App\Rag\EmbeddingProvider;
use App\Rag\VectorLiteral;
use App\Scene\Exceptions\InvalidLocationNodeInputException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class LocationNodeService
{
    public function __construct(private EmbeddingProvider $embeddings) {}

    /**
     * @return Collection<int, LocationNode>
     */
    public function listForLocation(ChronicleLocation $location): Collection
    {
        return LocationNode::query()
            ->where('chronicle_location_id', $location->id)
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array{name?: string}  $data
     */
    public function updateName(LocationNode $node, array $data): LocationNode
    {
        $name = array_key_exists('name', $data) ? trim((string) $data['name']) : $node->name;
        if ($name === '') {
            throw new InvalidLocationNodeInputException('Укажите название объекта.');
        }

        $reembed = $name !== $node->name;
        $node->name = $name;
        $node->save();

        if ($reembed) {
            $text = $node->description !== null && $node->description !== ''
                ? "{$node->name}: {$node->description}"
                : $node->name;
            $vector = $this->embeddings->embed($text);
            DB::table('location_nodes')
                ->where('id', $node->id)
                ->update([
                    'embedding' => DB::raw("'".VectorLiteral::fromArray($vector)."'::vector"),
                    'updated_at' => now(),
                ]);
        }

        return $node->refresh();
    }

    public function delete(LocationNode $node): void
    {
        $node->delete();
    }

    public function assertNodeInLocation(LocationNode $node, ChronicleLocation $location): void
    {
        if ((int) $node->chronicle_location_id !== (int) $location->id) {
            throw new InvalidLocationNodeInputException('Объект не принадлежит этому шаблону локации.');
        }
    }
}
