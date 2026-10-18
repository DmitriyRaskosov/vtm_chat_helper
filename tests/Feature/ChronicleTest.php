<?php

namespace Tests\Feature;

use App\Enums\ChronicleRole;
use App\Models\Chronicle;
use App\Models\ChronicleMember;
use App\Models\User;
use Tests\TestCase;

class ChronicleTest extends TestCase
{
    private const CHRONICLE_KEYS = [
        'id',
        'title',
        'description',
        'setting',
        'status',
        'role',
        'joined_at',
    ];

    public function test_guest_cannot_access_chronicle_routes(): void
    {
        $chronicle = Chronicle::factory()->create();

        $this->getJson('/api/chronicles')->assertStatus(401);
        $this->postJson('/api/chronicles', ['title' => 'Новая'])->assertStatus(401);
        $this->getJson("/api/chronicles/{$chronicle->id}")->assertStatus(401);
    }

    public function test_creator_lists_and_shows_chronicle_with_storyteller_membership(): void
    {
        $user = User::factory()->create();

        $create = $this->actingAs($user, 'sanctum')
            ->postJson('/api/chronicles', [
                'title' => 'Моя хроника',
                'description' => 'Описание',
                'setting' => 'Сиэтл',
            ]);

        $create->assertStatus(201);
        $create->assertJsonStructure(['chronicle' => self::CHRONICLE_KEYS]);
        $create->assertJsonPath('chronicle.title', 'Моя хроника');
        $create->assertJsonPath('chronicle.role', ChronicleRole::Storyteller->value);
        $create->assertJsonPath('chronicle.status', 'active');

        $chronicleId = (int) $create->json('chronicle.id');

        $this->assertDatabaseHas('chronicle_members', [
            'chronicle_id' => $chronicleId,
            'user_id' => $user->id,
            'role' => ChronicleRole::Storyteller->value,
        ]);
        $this->assertNull(
            ChronicleMember::query()
                ->where('chronicle_id', $chronicleId)
                ->where('user_id', $user->id)
                ->value('left_at'),
        );

        $index = $this->actingAs($user, 'sanctum')->getJson('/api/chronicles');
        $index->assertStatus(200);
        $index->assertJsonStructure(['chronicles' => ['*' => self::CHRONICLE_KEYS]]);
        $index->assertJsonCount(1, 'chronicles');
        $index->assertJsonPath('chronicles.0.id', $chronicleId);

        $show = $this->actingAs($user, 'sanctum')->getJson("/api/chronicles/{$chronicleId}");
        $show->assertStatus(200);
        $show->assertJsonStructure(['chronicle' => self::CHRONICLE_KEYS]);
        $show->assertJsonPath('chronicle.id', $chronicleId);
        $show->assertJsonPath('chronicle.role', ChronicleRole::Storyteller->value);
    }

    public function test_other_user_does_not_see_foreign_chronicle(): void
    {
        $creator = User::factory()->create();
        $other = User::factory()->create();

        $create = $this->actingAs($creator, 'sanctum')
            ->postJson('/api/chronicles', ['title' => 'Чужая хроника']);
        $create->assertStatus(201);
        $chronicleId = (int) $create->json('chronicle.id');

        $index = $this->actingAs($other, 'sanctum')->getJson('/api/chronicles');
        $index->assertStatus(200);
        $index->assertJsonPath('chronicles', []);

        $this->actingAs($other, 'sanctum')
            ->getJson("/api/chronicles/{$chronicleId}")
            ->assertStatus(404);
    }
}
