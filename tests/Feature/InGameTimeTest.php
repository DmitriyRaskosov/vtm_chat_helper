<?php

namespace Tests\Feature;

use App\Enums\ChronicleRole;
use App\Enums\SceneStatus;
use App\Models\Chronicle;
use App\Models\GameSession;
use App\Models\Scene;
use App\Models\User;
use Database\Seeders\CanonClanSeeder;
use Database\Seeders\CanonSectSeeder;
use Illuminate\Support\Carbon;
use Tests\Support\JoinsChronicle;
use Tests\TestCase;

class InGameTimeTest extends TestCase
{
    use JoinsChronicle;

    private User $storyteller;

    private Chronicle $chronicle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CanonSectSeeder::class);
        $this->seed(CanonClanSeeder::class);

        $this->storyteller = User::factory()->create();
        $this->chronicle = Chronicle::factory()->create([
            'title' => 'Тестовая хроника',
            'created_by' => $this->storyteller->id,
        ]);
        $this->joinChronicle($this->storyteller, $this->chronicle, ChronicleRole::Storyteller);
    }

    public function test_storyteller_can_update_in_game_time_and_player_cannot(): void
    {
        $current = Carbon::parse('1999-10-31 23:00:00');
        $next = Carbon::parse('1999-11-01 00:05:00');
        $this->chronicle->update(['current_in_game_date' => $current]);

        $session = GameSession::factory()->active()->create([
            'chronicle_id' => $this->chronicle->id,
            'title' => 'Сессия',
            'created_by' => $this->storyteller->id,
        ]);
        $active = Scene::factory()->active()->create([
            'game_session_id' => $session->id,
            'position' => 1,
            'started_at_in_game' => $current,
        ]);
        $draft = Scene::factory()->create([
            'game_session_id' => $session->id,
            'position' => 2,
            'status' => SceneStatus::Draft,
            'started_at_in_game' => $current,
        ]);

        $player = User::factory()->create();
        $this->joinChronicle($player, $this->chronicle, ChronicleRole::Player);

        $this->actingAs($player, 'sanctum')
            ->patchJson($this->chronicleApi($this->chronicle, 'time'), [
                'datetime' => $next->toIso8601String(),
            ])
            ->assertForbidden();

        $this->chronicle->refresh();
        $active->refresh();
        $this->assertTrue($this->chronicle->current_in_game_date?->equalTo($current));
        $this->assertTrue($active->started_at_in_game?->equalTo($current));

        $this->actingAs($this->storyteller, 'sanctum')
            ->patchJson($this->chronicleApi($this->chronicle, 'time'), [
                'datetime' => $next->toIso8601String(),
            ])
            ->assertOk()
            ->assertJsonPath('current_in_game_date', $next->toIso8601String())
            ->assertJsonPath('scene_id', $active->id)
            ->assertJsonPath('started_at_in_game', $next->toIso8601String());

        $this->chronicle->refresh();
        $active->refresh();
        $draft->refresh();
        $this->assertTrue($this->chronicle->current_in_game_date?->equalTo($next));
        $this->assertTrue($active->started_at_in_game?->equalTo($next));
        $this->assertTrue($draft->started_at_in_game?->equalTo($current));
    }
}
