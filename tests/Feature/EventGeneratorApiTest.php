<?php

namespace Tests\Feature;

use Tests\Support\AccessMatrixFixtures;
use Tests\TestCase;

class EventGeneratorApiTest extends TestCase
{
    private AccessMatrixFixtures $fixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtures = AccessMatrixFixtures::create($this);
    }

    public function test_storyteller_receives_stub_text(): void
    {
        $uri = $this->fixtures->resolveUri('chronicles/{chronicle}/scenes/{scene}/events/generate');

        $response = $this->actingAs($this->fixtures->storyteller, 'sanctum')->postJson($uri, [
            'action_description' => 'Прыжок через барьер',
            'attack_successes' => 3,
            'attack_botch' => false,
            'defense_successes' => 1,
            'defense_botch' => false,
        ]);

        $response->assertOk();
        $response->assertJsonPath('text', 'Сгенерировано: Прыжок через барьер');
    }

    public function test_player_cannot_generate_events(): void
    {
        $uri = $this->fixtures->resolveUri('chronicles/{chronicle}/scenes/{scene}/events/generate');

        $response = $this->actingAs($this->fixtures->playerOwner, 'sanctum')->postJson($uri, [
            'action_description' => 'Прыжок',
            'attack_successes' => 0,
            'defense_successes' => 0,
        ]);

        $response->assertForbidden();
    }
}
