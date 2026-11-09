<?php

namespace Tests\Feature;

use App\Models\SceneLog;
use Tests\Support\AccessMatrixFixtures;
use Tests\TestCase;

class DiceRollTest extends TestCase
{
    private AccessMatrixFixtures $fixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtures = AccessMatrixFixtures::create($this);
    }

    public function test_roll_during_combat_has_encounter_id(): void
    {
        $uri = $this->fixtures->resolveUri('chronicles/{chronicle}/scenes/{scene}/dice-rolls');

        $during = $this->actingAs($this->fixtures->storyteller, 'sanctum')->postJson($uri, [
            'pool' => 1,
            'difficulty' => 6,
        ]);
        $during->assertCreated();
        $during->assertJsonPath('log.combat_encounter_id', $this->fixtures->combatEncounter->id);

        $this->assertSame(
            $this->fixtures->combatEncounter->id,
            SceneLog::query()->findOrFail($during->json('log.id'))->combat_encounter_id,
        );

        $this->actingAs($this->fixtures->storyteller, 'sanctum')
            ->postJson('/api/combat/'.$this->fixtures->combatEncounter->id.'/finish')
            ->assertOk();

        $after = $this->actingAs($this->fixtures->storyteller, 'sanctum')->postJson($uri, [
            'pool' => 1,
            'difficulty' => 6,
        ]);
        $after->assertCreated();
        $after->assertJsonPath('log.combat_encounter_id', null);

        $this->assertNull(SceneLog::query()->findOrFail($after->json('log.id'))->combat_encounter_id);
    }
}
