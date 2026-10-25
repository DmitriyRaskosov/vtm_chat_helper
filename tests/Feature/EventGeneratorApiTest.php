<?php

namespace Tests\Feature;

use App\Models\SceneContext;
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

    public function test_storyteller_receives_generated_description(): void
    {
        $context = SceneContext::query()->firstOrNew([
            'scene_id' => $this->fixtures->scene->id,
        ]);
        $context->chronicle_id = $this->fixtures->chronicle->id;
        $context->environment_details = 'Пол бетонный';
        $context->save();

        $fake = $this->fakeChat()->push("Скамья бьёт в плечо.\n\nОн оседает на бетон.");

        $uri = $this->fixtures->resolveUri('chronicles/{chronicle}/scenes/{scene}/events/generate');

        $response = $this->actingAs($this->fixtures->storyteller, 'sanctum')->postJson($uri, [
            'action_description' => 'Порижана швыряет скамью',
            'attack_successes' => 3,
            'attack_botch' => false,
            'attack_willpower' => true,
            'defense_successes' => 0,
            'defense_botch' => true,
            'defense_willpower' => false,
        ]);

        $response->assertOk();
        $response->assertJsonPath('description', "Скамья бьёт в плечо.\n\nОн оседает на бетон.");

        $requests = $fake->requests();
        $this->assertCount(1, $requests);
        $this->assertSame('chatTurn', $requests[0]['method']);
        $this->assertSame(500, $requests[0]['options']['max_tokens']);
        $this->assertEqualsWithDelta(0.7, $requests[0]['options']['temperature'], 0.0001);
        $this->assertSame('system', $requests[0]['messages'][0]['role']);

        $system = $requests[0]['messages'][0]['content'];
        $this->assertIsString($system);
        $this->assertStringContainsString('Порижана швыряет скамью', $system);
        $this->assertStringContainsString('Пол бетонный', $system);
        $this->assertStringContainsString('Attack successes: 3', $system);
        $this->assertStringContainsString('Attack botch: false', $system);
        $this->assertStringContainsString('Attack willpower spent: true', $system);
        $this->assertStringContainsString('Defense successes: 0', $system);
        $this->assertStringContainsString('Defense botch: true', $system);
        $this->assertStringContainsString('Defense willpower spent: false', $system);
        $this->assertStringNotContainsString('{{action_description}}', $system);
    }

    public function test_missing_environment_uses_fallback(): void
    {
        $fake = $this->fakeChat()->push('Пустое место.');

        $uri = $this->fixtures->resolveUri('chronicles/{chronicle}/scenes/{scene}/events/generate');

        $response = $this->actingAs($this->fixtures->storyteller, 'sanctum')->postJson($uri, [
            'action_description' => 'Удар',
            'attack_successes' => 1,
            'defense_successes' => 1,
        ]);

        $response->assertOk();
        $system = $fake->requests()[0]['messages'][0]['content'];
        $this->assertIsString($system);
        $this->assertStringContainsString('No details provided', $system);
        $this->assertStringContainsString('Attack willpower spent: false', $system);
        $this->assertStringContainsString('Defense botch: false', $system);
    }

    public function test_llm_failure_returns_service_unavailable(): void
    {
        $this->fakeChat();

        $uri = $this->fixtures->resolveUri('chronicles/{chronicle}/scenes/{scene}/events/generate');

        $response = $this->actingAs($this->fixtures->storyteller, 'sanctum')->postJson($uri, [
            'action_description' => 'Удар',
            'attack_successes' => 1,
            'defense_successes' => 0,
        ]);

        $response->assertStatus(503);
        $response->assertJsonPath('message', 'Не удалось сгенерировать описание.');
    }

    public function test_willpower_flag_must_be_boolean(): void
    {
        $uri = $this->fixtures->resolveUri('chronicles/{chronicle}/scenes/{scene}/events/generate');

        $response = $this->actingAs($this->fixtures->storyteller, 'sanctum')->postJson($uri, [
            'action_description' => 'Удар',
            'attack_successes' => 1,
            'defense_successes' => 0,
            'attack_willpower' => 'maybe',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['attack_willpower']);
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
