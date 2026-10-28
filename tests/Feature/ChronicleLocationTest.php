<?php

namespace Tests\Feature;

use App\Models\ChronicleLocation;
use App\Models\SceneContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\AccessMatrixFixtures;
use Tests\TestCase;

class ChronicleLocationTest extends TestCase
{
    private AccessMatrixFixtures $fixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtures = AccessMatrixFixtures::create($this);
        Storage::fake('public');
    }

    public function test_storyteller_can_create_list_and_update_location(): void
    {
        $chronicleId = $this->fixtures->chronicle->id;

        $create = $this->actingAs($this->fixtures->storyteller, 'sanctum')
            ->postJson("/api/chronicles/{$chronicleId}/locations", [
                'name' => 'Кафе',
                'description' => 'Тусклый свет.',
                'environment_details' => 'Запах кофе.',
            ]);

        $create->assertCreated();
        $locationId = (int) $create->json('location.id');
        $this->assertDatabaseHas('chronicle_locations', [
            'id' => $locationId,
            'name' => 'Кафе',
        ]);

        $index = $this->actingAs($this->fixtures->storyteller, 'sanctum')
            ->getJson("/api/chronicles/{$chronicleId}/locations");
        $index->assertOk();
        $this->assertGreaterThanOrEqual(1, count($index->json('locations')));
        $this->assertNotNull(collect($index->json('locations'))->firstWhere('id', $locationId));

        $update = $this->actingAs($this->fixtures->storyteller, 'sanctum')
            ->patchJson("/api/locations/{$locationId}", [
                'name' => 'Кафе «Ноктюрн»',
            ]);
        $update->assertOk();
        $this->assertSame('Кафе «Ноктюрн»', $update->json('location.name'));
    }

    public function test_apply_template_overwrites_scene_context(): void
    {
        $sceneId = $this->fixtures->scene->id;
        $chronicleId = $this->fixtures->chronicle->id;

        SceneContext::query()->where('scene_id', $sceneId)->update([
            'situation' => 'Старая ситуация',
            'environment_details' => 'Старое окружение',
        ]);

        $location = ChronicleLocation::query()->create([
            'chronicle_id' => $chronicleId,
            'name' => 'Шаблон',
            'description' => 'Новая ситуация',
            'environment_details' => 'Новое окружение',
        ]);

        $revision = (int) SceneContext::query()->where('scene_id', $sceneId)->value('revision');

        $this->actingAs($this->fixtures->storyteller, 'sanctum')
            ->putJson("/api/scenes/{$sceneId}/context", [
                'expected_revision' => $revision,
                'situation' => 'Изменено вручную',
            ])
            ->assertOk();

        $revision = (int) SceneContext::query()->where('scene_id', $sceneId)->value('revision');

        $apply = $this->actingAs($this->fixtures->storyteller, 'sanctum')
            ->postJson("/api/chronicles/{$chronicleId}/scenes/{$sceneId}/context/apply-template", [
                'location_id' => $location->id,
                'expected_revision' => $revision,
            ]);

        $apply->assertOk();
        $this->assertSame('Новая ситуация', $apply->json('context.situation'));
        $this->assertSame('Новое окружение', $apply->json('context.environment_details'));

        $this->assertDatabaseHas('scene_contexts', [
            'scene_id' => $sceneId,
            'situation' => 'Новая ситуация',
            'environment_details' => 'Новое окружение',
        ]);
    }

    public function test_create_from_scene_copies_context_fields(): void
    {
        $sceneId = $this->fixtures->scene->id;
        $chronicleId = $this->fixtures->chronicle->id;

        $context = SceneContext::query()->firstOrNew(['scene_id' => $sceneId], [
            'chronicle_id' => $chronicleId,
            'revision' => 0,
        ]);
        $context->situation = 'Сцена: двор';
        $context->environment_details = 'Дождь';
        $context->save();

        $response = $this->actingAs($this->fixtures->storyteller, 'sanctum')
            ->postJson("/api/chronicles/{$chronicleId}/locations", [
                'name' => 'Из сцены',
                'scene_id' => $sceneId,
            ]);

        $response->assertCreated();
        $this->assertSame('Сцена: двор', $response->json('location.description'));
        $this->assertSame('Дождь', $response->json('location.environment_details'));
    }

    public function test_location_image_upload(): void
    {
        $location = ChronicleLocation::query()->create([
            'chronicle_id' => $this->fixtures->chronicle->id,
            'name' => 'С фото',
        ]);

        $file = UploadedFile::fake()->image('loc.png');

        $this->actingAs($this->fixtures->storyteller, 'sanctum')
            ->postJson("/api/locations/{$location->id}/image", [
                'image' => $file,
            ])
            ->assertOk();

        $location->refresh();
        $this->assertNotNull($location->image_path);
        Storage::disk('public')->assertExists($location->image_path);
    }

    public function test_player_cannot_manage_locations(): void
    {
        $chronicleId = $this->fixtures->chronicle->id;

        $this->actingAs($this->fixtures->playerOwner, 'sanctum')
            ->getJson("/api/chronicles/{$chronicleId}/locations")
            ->assertForbidden();
    }
}
