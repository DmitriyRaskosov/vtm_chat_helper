<?php

namespace Tests\Feature;

use App\Context\ContextAssembly;
use App\Context\ContextPass;
use App\Context\ContextRequest;
use App\Context\Providers\DirectRelationsProvider;
use App\Enums\ChronicleRole;
use App\Enums\WorldRelationKind;
use App\Models\Character;
use App\Models\Chronicle;
use App\Models\GameSession;
use App\Models\Scene;
use App\Models\User;
use App\Models\WorldEntity;
use App\Models\WorldRelation;
use Database\Seeders\CanonClanSeeder;
use Database\Seeders\CanonSectSeeder;
use Tests\Support\JoinsChronicle;
use Tests\TestCase;

class DirectRelationsProviderTest extends TestCase
{
    use JoinsChronicle;

    private Chronicle $chronicle;

    public function test_assemble_includes_active_neighbor_relation(): void
    {
        $this->seed(CanonSectSeeder::class);
        $this->seed(CanonClanSeeder::class);

        $storyteller = User::factory()->create();
        $this->chronicle = Chronicle::factory()->create([
            'title' => 'Тестовая хроника',
            'created_by' => $storyteller->id,
        ]);
        $this->joinChronicle($storyteller, $this->chronicle, ChronicleRole::Storyteller);

        $gameSession = GameSession::factory()->active()->create([
            'chronicle_id' => $this->chronicle->id,
            'title' => 'Сессия',
            'created_by' => $storyteller->id,
        ]);

        $scene = Scene::factory()->active()->create([
            'game_session_id' => $gameSession->id,
            'position' => 1,
            'title' => 'Сцена',
        ]);

        $sourceResponse = $this->actingAs($storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'characters'), [
                'canonical_name' => 'Виктор',
                'character_type' => 'npc',
            ]);
        $sourceResponse->assertStatus(201);
        $sourceCharacter = Character::query()->findOrFail($sourceResponse->json('character.id'));
        $sourceEntity = WorldEntity::query()->findOrFail($sourceCharacter->id);

        $targetResponse = $this->actingAs($storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'characters'), [
                'canonical_name' => 'Мария',
                'character_type' => 'npc',
            ]);
        $targetResponse->assertStatus(201);
        $targetEntity = WorldEntity::query()->findOrFail($targetResponse->json('character.id'));

        WorldRelation::query()->create([
            'chronicle_id' => $this->chronicle->id,
            'source_type' => 'character',
            'source_id' => $sourceEntity->id,
            'target_type' => 'character',
            'target_id' => $targetEntity->id,
            'relation' => WorldRelationKind::Knows,
            'source_of_truth' => 'chronicle',
            'weight' => 1.0,
            'valid_from' => now(),
            'provenance' => [],
        ]);

        $assembly = new ContextAssembly(
            new ContextRequest(
                npcName: 'Виктор',
                prompt: 'test',
                sceneId: $scene->id,
                draftCount: 1,
                pass: ContextPass::Reply,
            ),
            $scene,
            $gameSession,
            $this->chronicle,
            $sourceCharacter,
            $sourceEntity,
        );

        $section = app(DirectRelationsProvider::class)->assemble($assembly, 4000);

        $this->assertTrue($section->included);
        $this->assertStringContainsString('Мария', $section->content);
        $this->assertStringContainsString('knows', $section->content);
    }
}
