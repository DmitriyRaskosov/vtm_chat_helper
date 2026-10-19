<?php

namespace App\Scene;

use App\Dice\DiceRollResult;
use App\Enums\SceneLogType;
use App\Enums\SceneLogVisibility;
use App\Models\Character;
use App\Models\Scene;
use App\Models\SceneLog;
use App\Models\User;
use Illuminate\Support\Collection;

final class SceneLogService
{
    public function logDiceRoll(
        Scene $scene,
        User $user,
        ?Character $character,
        DiceRollResult $result,
        bool $isHidden,
    ): SceneLog {
        $visibility = $isHidden
            ? SceneLogVisibility::StorytellerOnly
            : SceneLogVisibility::Public;

        $character?->loadMissing('entity');
        $label = $this->characterLabel($character);

        $diceList = implode(', ', array_map('strval', $result->dice));
        $outcomeLabel = strtoupper($result->outcome->value);

        $message = sprintf(
            '%s: бросок %dd%d (сл. %d). Выпало: %s. Итог: %s',
            $label,
            $result->pool,
            $result->diceType,
            $result->difficulty,
            $diceList,
            $outcomeLabel,
        );

        return SceneLog::query()->create([
            'scene_id' => $scene->id,
            'user_id' => $user->id,
            'character_id' => $character?->id,
            'type' => SceneLogType::DiceRoll,
            'visibility' => $visibility,
            'message' => $message,
            'payload' => $result->toPayload(),
        ]);
    }

    /**
     * @return Collection<int, SceneLog>
     */
    public function listForScene(Scene $scene, bool $includeStorytellerOnly, ?int $afterId = null): Collection
    {
        $query = SceneLog::query()
            ->where('scene_id', $scene->id)
            ->orderBy('id');

        if ($afterId !== null) {
            $query->where('id', '>', $afterId);
        }

        if (! $includeStorytellerOnly) {
            $query->where('visibility', SceneLogVisibility::Public);
        }

        return $query->get();
    }

    private function characterLabel(?Character $character): string
    {
        if ($character === null) {
            return 'Свободный бросок';
        }

        $name = $character->entity?->canonical_name;

        return $name !== null && $name !== ''
            ? $name
            : 'Персонаж #'.$character->id;
    }
}
