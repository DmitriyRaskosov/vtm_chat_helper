<?php

namespace Tests\Feature;

use App\Context\ContextBuilder;
use App\Enums\SceneParticipantRole;
use App\Enums\SceneStatus;
use App\Enums\WorldEntityType;
use App\Models\Character;
use App\Models\Chronicle;
use App\Models\Scene;
use App\Models\SceneContext;
use App\Models\User;
use App\Scene\SceneContextRevisionException;
use App\Scene\SceneContextService;
use App\Scene\SceneFrozenException;
use App\Scene\SceneParticipantService;
use App\World\MixedChronicleException;
use App\World\WorldEntityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SceneContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_apply_writes_canonical_snapshot_and_increments_revision(): void
    {
        $scene = Scene::query()->active()->with('gameSession.chronicle')->firstOrFail();
        $storyteller = User::factory()->storyteller()->create();
        $location = $this->app->make(WorldEntityService::class)->create(
            $scene->gameSession->chronicle,
            WorldEntityType::Location,
            'Пражская опера',
        );
        $service = $this->app->make(SceneContextService::class);

        $context = $service->apply(
            $scene,
            [
                'location_entity_id' => $location->id,
                'atmosphere' => 'Туман и свечи.',
                'situation' => 'Князь ждёт опоздавших.',
                'storyteller_notes' => 'Не раскрывать Камарилью.',
            ],
            expectedRevision: 0,
            updatedBy: $storyteller,
        );

        $this->assertSame(1, $context->revision);
        $this->assertSame($location->id, $context->location_entity_id);
        $this->assertSame('Туман и свечи.', $context->atmosphere);
        $this->assertSame($storyteller->id, $context->updated_by);
        $this->assertNull($context->frozen_revision);
    }

    public function test_stale_revision_is_rejected(): void
    {
        $scene = Scene::query()->active()->firstOrFail();
        $service = $this->app->make(SceneContextService::class);
        $service->apply($scene, ['atmosphere' => 'Тишина.'], expectedRevision: 0);

        $this->expectException(SceneContextRevisionException::class);
        $service->apply($scene, ['atmosphere' => 'Шум.'], expectedRevision: 0);
    }

    public function test_location_from_another_chronicle_is_rejected(): void
    {
        $scene = Scene::query()->active()->with('gameSession')->firstOrFail();
        $other = Chronicle::factory()->create();
        $foreign = $this->app->make(WorldEntityService::class)->create(
            $other,
            WorldEntityType::Location,
            'Берлин',
        );

        $this->expectException(MixedChronicleException::class);
        $this->app->make(SceneContextService::class)->apply(
            $scene,
            ['location_entity_id' => $foreign->id],
            expectedRevision: 0,
        );
    }

    public function test_close_freezes_context_without_calling_ollama(): void
    {
        $scene = Scene::query()->active()->firstOrFail();
        $storyteller = User::factory()->storyteller()->create();
        $this->app->make(SceneContextService::class)->apply(
            $scene,
            ['atmosphere' => 'Холодный мрамор.'],
            expectedRevision: 0,
            updatedBy: $storyteller,
        );

        Http::fake();
        Sanctum::actingAs($storyteller);

        $this->patchJson("/api/scenes/{$scene->id}/close")
            ->assertOk()
            ->assertJsonPath('scene.status', SceneStatus::Closed->value);

        Http::assertNothingSent();
        $this->assertSame(1, SceneContext::query()->where('scene_id', $scene->id)->value('frozen_revision'));

        $this->expectException(SceneFrozenException::class);
        $this->app->make(SceneContextService::class)->apply(
            $scene->refresh(),
            ['atmosphere' => 'После закрытия.'],
            expectedRevision: 1,
        );
    }

    public function test_storyteller_can_read_and_update_context_over_http(): void
    {
        $scene = Scene::query()->active()->firstOrFail();
        $storyteller = User::factory()->storyteller()->create();
        Sanctum::actingAs($storyteller);

        $this->getJson("/api/scenes/{$scene->id}/context")
            ->assertOk()
            ->assertJsonPath('context.scene_id', $scene->id)
            ->assertJsonPath('context.revision', 0)
            ->assertJsonPath('context.atmosphere', null);

        $this->putJson("/api/scenes/{$scene->id}/context", [
            'expected_revision' => 0,
            'atmosphere' => 'Дождь по стеклу.',
            'situation' => 'Гости собираются.',
        ])
            ->assertOk()
            ->assertJsonPath('context.atmosphere', 'Дождь по стеклу.')
            ->assertJsonPath('context.revision', 1);

        $this->putJson("/api/scenes/{$scene->id}/context", [
            'expected_revision' => 0,
            'atmosphere' => 'Устарело.',
        ])->assertStatus(409);
    }

    public function test_player_cannot_read_scene_context(): void
    {
        $scene = Scene::query()->active()->firstOrFail();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/scenes/{$scene->id}/context")->assertForbidden();
    }

    public function test_assembler_reads_scene_context_and_frozen_snapshot(): void
    {
        $scene = Scene::query()->active()->with('gameSession.chronicle')->firstOrFail();
        $chronicle = $scene->gameSession->chronicle;
        $entities = $this->app->make(WorldEntityService::class);
        $location = $entities->create($chronicle, WorldEntityType::Location, 'Элизиум оперы');
        $npc = Character::query()->findOrFail(
            $entities->create($chronicle, WorldEntityType::Character, 'Виктория-сцена')->id,
        );

        $this->app->make(SceneContextService::class)->apply($scene, [
            'location_entity_id' => $location->id,
            'atmosphere' => 'Запах ладана.',
            'situation' => 'Князь на балконе.',
            'storyteller_notes' => 'Держать тон холодным.',
        ], expectedRevision: 0);
        $this->app->make(SceneParticipantService::class)->enter(
            $scene,
            $npc,
            SceneParticipantRole::Npc,
        );

        $build = $this->app->make(ContextBuilder::class)->build(
            'Виктория-сцена',
            'Описать зал.',
            $scene->id,
            3,
            User::factory()->storyteller()->create()->id,
            (int) $scene->game_session_id,
            $npc->id,
        );
        $user = $build->messages[1]['content'];

        $this->assertStringContainsString('Location: Элизиум оперы', $user);
        $this->assertStringContainsString('Atmosphere: Запах ладана.', $user);
        $this->assertStringContainsString('Situation: Князь на балконе.', $user);
        $this->assertStringContainsString('Storyteller notes: Держать тон холодным.', $user);
        $this->assertStringContainsString('Present: Виктория-сцена (npc, visible)', $user);
        $this->assertSame(1, $build->metadata['data_versions']['context_revision']);
        $this->assertSame([$npc->id], $build->metadata['sections']['scene']['provenance']['participant_character_ids']);

        $storyteller = User::factory()->storyteller()->create();
        Sanctum::actingAs($storyteller);
        $this->patchJson("/api/scenes/{$scene->id}/close")->assertOk();

        $frozen = $this->app->make(ContextBuilder::class)->build(
            'Виктория-сцена',
            'Описать зал.',
            $scene->id,
            3,
            $storyteller->id,
            (int) $scene->game_session_id,
            $npc->id,
        );
        $this->assertStringContainsString('Atmosphere: Запах ладана.', $frozen->messages[1]['content']);
        $this->assertSame(1, $frozen->metadata['sections']['scene']['provenance']['frozen_revision']);
    }
}
