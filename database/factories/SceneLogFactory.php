<?php

namespace Database\Factories;

use App\Enums\SceneLogType;
use App\Enums\SceneLogVisibility;
use App\Models\Scene;
use App\Models\SceneLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SceneLog>
 */
class SceneLogFactory extends Factory
{
    protected $model = SceneLog::class;

    public function definition(): array
    {
        return [
            'scene_id' => Scene::factory(),
            'user_id' => User::factory(),
            'character_id' => null,
            'type' => SceneLogType::DiceRoll,
            'visibility' => SceneLogVisibility::Public,
            'message' => 'Свободный бросок: бросок 1d10 (сл. 6). Выпало: 5. Итог: SUCCESS',
            'payload' => [
                'pool' => 1,
                'difficulty' => 6,
                'dice_type' => 10,
                'dice' => [5],
                'successes' => 0,
                'ones' => 0,
                'net' => 0,
                'outcome' => 'failure',
            ],
        ];
    }
}
