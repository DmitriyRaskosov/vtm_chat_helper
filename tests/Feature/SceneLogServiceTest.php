<?php

namespace Tests\Feature;

use App\Dice\DiceRollResult;
use App\Enums\DiceRollOutcome;
use App\Enums\SceneLogVisibility;
use App\Models\Character;
use App\Models\Chronicle;
use App\Models\GameSession;
use App\Models\Scene;
use App\Models\User;
use App\Scene\SceneLogService;
use Database\Seeders\CanonClanSeeder;
use Database\Seeders\CanonDisciplineSeeder;
use Database\Seeders\CanonSectSeeder;
use Tests\TestCase;

class SceneLogServiceTest extends TestCase
{
    private SceneLogService $logs;

    private Chronicle $chronicle;

    private Scene $scene;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CanonSectSeeder::class);
        $this->seed(CanonClanSeeder::class);
        $this->seed(CanonDisciplineSeeder::class);

        $this->logs = $this->app->make(SceneLogService::class);

        $this->user = User::factory()->create();
        $this->chronicle = Chronicle::factory()->create(['created_by' => $this->user->id]);
        $session = GameSession::factory()->active()->create(['chronicle_id' => $this->chronicle->id]);
        $this->scene = Scene::factory()->active()->forGameSession($session)->create();
    }

    public function test_log_dice_roll_free_throw_message(): void
    {
        $result = DiceRollResult::fromDice(5, 9, 10, [4, 7, 1, 5, 3]);

        $log = $this->logs->logDiceRoll($this->scene, $this->user, null, $result, false);

        $this->assertStringContainsString('Свободный бросок: бросок 5d10 (сл. 9)', $log->message);
        $this->assertStringContainsString('4, 7, 1, 5, 3', $log->message);
        $this->assertStringContainsString('Итог: BOTCH', $log->message);
        $this->assertSame(SceneLogVisibility::Public, $log->visibility);
        $this->assertSame(DiceRollOutcome::Botch->value, $log->payload['outcome']);
    }

    public function test_log_dice_roll_uses_character_name(): void
    {
        $character = Character::factory()->create([
            'chronicle_id' => $this->chronicle->id,
            'canonical_name' => 'Абрахам',
        ]);
        $character->load('entity');

        $result = DiceRollResult::fromDice(2, 6, 10, [8, 3]);

        $log = $this->logs->logDiceRoll($this->scene, $this->user, $character, $result, true);

        $this->assertStringStartsWith('Абрахам:', $log->message);
        $this->assertSame(SceneLogVisibility::StorytellerOnly, $log->visibility);
    }

    public function test_list_for_scene_filters_hidden_for_players(): void
    {
        $public = $this->logs->logDiceRoll(
            $this->scene,
            $this->user,
            null,
            DiceRollResult::fromDice(1, 6, 10, [5]),
            false,
        );
        $this->logs->logDiceRoll(
            $this->scene,
            $this->user,
            null,
            DiceRollResult::fromDice(1, 6, 10, [1]),
            true,
        );

        $playerView = $this->logs->listForScene($this->scene, false);
        $storytellerView = $this->logs->listForScene($this->scene, true);

        $this->assertSame([$public->id], $playerView->pluck('id')->all());
        $this->assertCount(2, $storytellerView);
    }
}
