<?php

namespace Tests\Feature;

use App\Models\LocationNode;
use Tests\Support\AccessMatrixFixtures;
use Tests\TestCase;

class LocationNodeApiTest extends TestCase
{
    private AccessMatrixFixtures $fixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtures = AccessMatrixFixtures::create($this);
        $this->fixtures->ensureLocationNode();
        $this->fakeEmbeddings();
    }

    public function test_storyteller_can_list_location_nodes(): void
    {
        $locationId = $this->fixtures->chronicleLocation->id;

        $index = $this->actingAs($this->fixtures->storyteller, 'sanctum')
            ->getJson("/api/locations/{$locationId}/location-nodes");

        $index->assertOk();
        $index->assertJsonPath('location_nodes.0.name', 'Объект матрицы');
    }

    public function test_storyteller_can_update_name_and_delete_location_node(): void
    {
        $locationId = $this->fixtures->chronicleLocation->id;
        $node = $this->fixtures->ensureLocationNode();

        $update = $this->actingAs($this->fixtures->storyteller, 'sanctum')
            ->patchJson(
                "/api/locations/{$locationId}/location-nodes/{$node->id}",
                ['name' => 'Объект переименован'],
            );

        $update->assertOk();
        $update->assertJsonPath('location_node.name', 'Объект переименован');

        $delete = $this->actingAs($this->fixtures->storyteller, 'sanctum')
            ->deleteJson("/api/locations/{$locationId}/location-nodes/{$node->id}");

        $delete->assertOk();
        $this->assertNull(LocationNode::query()->find($node->id));
    }

    public function test_player_cannot_manage_location_nodes(): void
    {
        $locationId = $this->fixtures->chronicleLocation->id;

        $response = $this->actingAs($this->fixtures->playerOwner, 'sanctum')
            ->getJson("/api/locations/{$locationId}/location-nodes");

        $response->assertForbidden();
    }
}
