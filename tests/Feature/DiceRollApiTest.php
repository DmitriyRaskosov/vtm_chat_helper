<?php

namespace Tests\Feature;

use Tests\Support\AccessMatrixFixtures;
use Tests\TestCase;

class DiceRollApiTest extends TestCase
{
    private AccessMatrixFixtures $fixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtures = AccessMatrixFixtures::create($this);
    }

    public function test_player_can_roll_and_see_public_log(): void
    {
        $uri = $this->fixtures->resolveUri('chronicles/{chronicle}/scenes/{scene}/dice-rolls');

        $response = $this->actingAs($this->fixtures->playerOwner, 'sanctum')->postJson($uri, [
            'pool' => 2,
            'difficulty' => 6,
        ]);

        $response->assertCreated();
        $response->assertJsonStructure([
            'log' => ['id', 'message', 'visibility', 'payload'],
            'result' => ['dice', 'outcome'],
        ]);
        $this->assertSame('public', $response->json('log.visibility'));
    }

    public function test_hidden_roll_not_visible_to_other_player(): void
    {
        $storeUri = $this->fixtures->resolveUri('chronicles/{chronicle}/scenes/{scene}/dice-rolls');
        $logsUri = $this->fixtures->resolveUri('chronicles/{chronicle}/scenes/{scene}/logs');

        $hidden = $this->actingAs($this->fixtures->storyteller, 'sanctum')->postJson($storeUri, [
            'pool' => 1,
            'difficulty' => 6,
            'character_id' => $this->fixtures->npc->id,
            'is_hidden' => true,
        ]);
        $hidden->assertCreated();
        $logId = (int) $hidden->json('log.id');

        $playerLogs = $this->actingAs($this->fixtures->playerOwner, 'sanctum')->getJson($logsUri);
        $playerLogs->assertOk();
        $this->assertNotContains($logId, collect($playerLogs->json('logs'))->pluck('id')->all());

        $storytellerLogs = $this->actingAs($this->fixtures->storyteller, 'sanctum')->getJson($logsUri);
        $storytellerLogs->assertOk();
        $this->assertContains($logId, collect($storytellerLogs->json('logs'))->pluck('id')->all());
    }

    public function test_player_cannot_roll_for_foreign_pc(): void
    {
        $uri = $this->fixtures->resolveUri('chronicles/{chronicle}/scenes/{scene}/dice-rolls');

        $response = $this->actingAs($this->fixtures->playerForeign, 'sanctum')->postJson($uri, [
            'pool' => 1,
            'difficulty' => 6,
            'character_id' => $this->fixtures->playerCharacter->id,
        ]);

        $response->assertForbidden();
    }

    public function test_player_cannot_use_hidden_flag(): void
    {
        $uri = $this->fixtures->resolveUri('chronicles/{chronicle}/scenes/{scene}/dice-rolls');

        $response = $this->actingAs($this->fixtures->playerOwner, 'sanctum')->postJson($uri, [
            'pool' => 1,
            'difficulty' => 6,
            'is_hidden' => true,
        ]);

        $response->assertStatus(422);
    }

    public function test_roll_with_comment_and_sum_mode(): void
    {
        $uri = $this->fixtures->resolveUri('chronicles/{chronicle}/scenes/{scene}/dice-rolls');

        $response = $this->actingAs($this->fixtures->storyteller, 'sanctum')->postJson($uri, [
            'pool' => 3,
            'dice_type' => 10,
            'mode' => 'sum',
            'comment' => 'Уклонение от выстрела',
            'character_id' => $this->fixtures->npc->id,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('log.comment', 'Уклонение от выстрела');
        $response->assertJsonPath('result.mode', 'sum');
        $this->assertIsInt($response->json('result.sum'));
        $this->assertSame($response->json('result.sum'), $response->json('result.net'));
    }

    public function test_wrong_chronicle_scene_returns_not_found(): void
    {
        $otherChronicle = $this->fixtures->otherChronicle;
        $scene = $this->fixtures->scene;

        $uri = '/api/chronicles/'.$otherChronicle->id.'/scenes/'.$scene->id.'/dice-rolls';

        $response = $this->actingAs($this->fixtures->storyteller, 'sanctum')->postJson($uri, [
            'pool' => 1,
            'difficulty' => 6,
        ]);

        $response->assertNotFound();
    }
}
