<?php

namespace Tests\Feature;

use App\Enums\SceneParticipantRole;
use App\Enums\WorldEntityType;
use App\Models\Character;
use App\Models\Chronicle;
use App\Models\Scene;
use App\Models\SceneParticipant;
use App\Models\User;
use App\Scene\SceneFrozenException;
use App\Scene\SceneParticipantService;
use App\World\MixedChronicleException;
use App\World\WorldEntityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SceneParticipantTest extends TestCase
{
    use RefreshDatabase;

    public function test_enter_leave_and_reenter_keep_one_current_row(): void
    {
        $scene = Scene::query()->active()->with('gameSession.chronicle')->firstOrFail();
        $npc = Character::query()->findOrFail(
            $this->app->make(WorldEntityService::class)->create(
                $scene->gameSession->chronicle,
                WorldEntityType::Character,
                'Маркус',
            )->id,
        );
        $service = $this->app->make(SceneParticipantService::class);

        $entered = $service->enter($scene, $npc, SceneParticipantRole::Npc);
        $again = $service->enter($scene, $npc, SceneParticipantRole::Extra);
        $this->assertTrue($entered->is($again));
        $this->assertSame(SceneParticipantRole::Npc, $again->role);
        $this->assertSame(1, SceneParticipant::query()->where('scene_id', $scene->id)->where('is_current', true)->count());

        $left = $service->leave($scene, $npc);
        $this->assertFalse($left->is_current);
        $this->assertNotNull($left->left_at);

        $reentered = $service->enter($scene, $npc, SceneParticipantRole::Player, visible: false);
        $this->assertTrue($reentered->is_current);
        $this->assertSame(SceneParticipantRole::Player, $reentered->role);
        $this->assertFalse($reentered->visible);
        $this->assertSame(2, SceneParticipant::query()->where('scene_id', $scene->id)->count());
        $this->assertSame(1, SceneParticipant::query()->where('scene_id', $scene->id)->where('is_current', true)->count());
    }

    public function test_character_from_another_chronicle_cannot_enter(): void
    {
        $scene = Scene::query()->active()->firstOrFail();
        $foreign = Character::query()->findOrFail(
            $this->app->make(WorldEntityService::class)->create(
                Chronicle::factory()->create(),
                WorldEntityType::Character,
                'Чужак',
            )->id,
        );

        $this->expectException(MixedChronicleException::class);
        $this->app->make(SceneParticipantService::class)->enter(
            $scene,
            $foreign,
            SceneParticipantRole::Npc,
        );
    }

    public function test_closed_scene_rejects_participant_changes(): void
    {
        $scene = Scene::query()->active()->with('gameSession.chronicle')->firstOrFail();
        $npc = Character::query()->findOrFail(
            $this->app->make(WorldEntityService::class)->create(
                $scene->gameSession->chronicle,
                WorldEntityType::Character,
                'Гость',
            )->id,
        );
        $service = $this->app->make(SceneParticipantService::class);
        $service->enter($scene, $npc, SceneParticipantRole::Npc);

        $storyteller = User::factory()->storyteller()->create();
        Sanctum::actingAs($storyteller);
        $this->patchJson("/api/scenes/{$scene->id}/close")->assertOk();

        $this->expectException(SceneFrozenException::class);
        $service->leave($scene->refresh(), $npc);
    }

    public function test_storyteller_can_list_enter_and_leave_over_http(): void
    {
        $scene = Scene::query()->active()->with('gameSession.chronicle')->firstOrFail();
        $npc = Character::query()->findOrFail(
            $this->app->make(WorldEntityService::class)->create(
                $scene->gameSession->chronicle,
                WorldEntityType::Character,
                'Ирена',
            )->id,
        );
        $storyteller = User::factory()->storyteller()->create();
        Sanctum::actingAs($storyteller);

        $this->postJson("/api/scenes/{$scene->id}/participants", [
            'character_id' => $npc->id,
            'role' => 'npc',
        ])
            ->assertCreated()
            ->assertJsonPath('participant.character_id', $npc->id)
            ->assertJsonPath('participant.character_name', 'Ирена')
            ->assertJsonPath('participant.character_type', 'npc')
            ->assertJsonPath('participant.is_current', true);

        $this->getJson("/api/scenes/{$scene->id}/participants")
            ->assertOk()
            ->assertJsonCount(1, 'participants');

        $this->patchJson("/api/scenes/{$scene->id}/participants/{$npc->id}")
            ->assertOk()
            ->assertJsonPath('participant.is_current', false);

        $this->patchJson("/api/scenes/{$scene->id}/participants/{$npc->id}")
            ->assertStatus(422);
    }

    public function test_player_cannot_mutate_participants(): void
    {
        $scene = Scene::query()->active()->firstOrFail();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/scenes/{$scene->id}/participants")->assertForbidden();
    }
}
