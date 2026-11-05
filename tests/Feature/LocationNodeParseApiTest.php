<?php

namespace Tests\Feature;

use App\Models\LocationNode;
use Tests\Support\AccessMatrixFixtures;
use Tests\TestCase;

class LocationNodeParseApiTest extends TestCase
{
    private AccessMatrixFixtures $fixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtures = AccessMatrixFixtures::create($this);
        $this->fakeChat()->push(json_encode([
            'nodes' => [
                ['name' => 'Зал', 'description' => 'просторный', 'children' => []],
            ],
        ], JSON_UNESCAPED_UNICODE));
        $this->fakeEmbeddings();
    }

    public function test_storyteller_can_parse_environment_into_location_nodes(): void
    {
        $locationId = $this->fixtures->chronicleLocation->id;

        $response = $this->actingAs($this->fixtures->storyteller, 'sanctum')
            ->postJson("/api/locations/{$locationId}/location-nodes/parse", [
                'environment_details' => 'Большой зал с колоннами.',
            ]);

        $response->assertOk();
        $response->assertJsonPath('location_nodes.0.name', 'Зал');
        $this->assertSame(1, LocationNode::query()->where('chronicle_location_id', $locationId)->count());
    }

    public function test_parse_requires_environment_details(): void
    {
        $locationId = $this->fixtures->chronicleLocation->id;

        $response = $this->actingAs($this->fixtures->storyteller, 'sanctum')
            ->postJson("/api/locations/{$locationId}/location-nodes/parse", []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['environment_details']);
    }

    public function test_player_cannot_parse_location_nodes(): void
    {
        $locationId = $this->fixtures->chronicleLocation->id;

        $response = $this->actingAs($this->fixtures->playerOwner, 'sanctum')
            ->postJson("/api/locations/{$locationId}/location-nodes/parse", [
                'environment_details' => 'Текст.',
            ]);

        $response->assertForbidden();
    }
}
