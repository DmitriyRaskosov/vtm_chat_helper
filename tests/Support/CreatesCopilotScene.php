<?php

namespace Tests\Support;

use App\Enums\ChronicleRole;
use App\Models\Character;
use App\Models\Chronicle;
use App\Models\GameSession;
use App\Models\Scene;
use App\Models\User;
use Database\Seeders\CanonClanSeeder;
use Database\Seeders\CanonSectSeeder;

trait CreatesCopilotScene
{
    use JoinsChronicle;

    protected User $storyteller;

    protected Chronicle $chronicle;

    protected GameSession $gameSession;

    protected Scene $scene;

    protected Character $npc;

    protected int $sceneId;

    protected int $npcId;

    protected function setUpCopilotScene(): void
    {
        $this->seed(CanonSectSeeder::class);
        $this->seed(CanonClanSeeder::class);

        $this->storyteller = User::factory()->create();
        $this->chronicle = Chronicle::factory()->create([
            'title' => 'Тестовая хроника',
            'created_by' => $this->storyteller->id,
        ]);
        $this->joinChronicle($this->storyteller, $this->chronicle, ChronicleRole::Storyteller);
        $this->gameSession = GameSession::factory()->active()->create([
            'chronicle_id' => $this->chronicle->id,
            'title' => 'Тестовая сессия',
            'created_by' => $this->storyteller->id,
        ]);

        $created = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/game-sessions/'.$this->gameSession->id.'/scenes', [
                'title' => 'Сцена',
            ]);
        $created->assertStatus(201);
        $this->sceneId = (int) $created->json('scene.id');
        $this->scene = Scene::query()->findOrFail($this->sceneId);

        $npcResponse = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'characters'), [
                'canonical_name' => 'Иван',
                'character_type' => 'npc',
            ]);
        $npcResponse->assertStatus(201);
        $this->npcId = (int) $npcResponse->json('character.id');
        $this->npc = Character::query()->findOrFail($this->npcId);
    }
}
