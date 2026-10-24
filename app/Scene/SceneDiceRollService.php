<?php

namespace App\Scene;

use App\Character\Exceptions\CharacterNotInChronicleException;
use App\Dice\DiceRollResult;
use App\Dice\DiceService;
use App\Enums\DiceRollMode;
use App\Models\Character;
use App\Models\Chronicle;
use App\Models\Scene;
use App\Models\SceneLog;
use App\Models\User;
use App\Scene\Exceptions\SceneDiceRollForbiddenException;

final class SceneDiceRollService
{
    public function __construct(
        private DiceService $dice,
        private SceneLogService $sceneLogs,
    ) {}

    /**
     * @return array{result: DiceRollResult, log: SceneLog}
     */
    public function roll(
        Chronicle $chronicle,
        Scene $scene,
        User $user,
        int $pool,
        int $difficulty,
        int $diceType,
        ?int $characterId,
        bool $isHidden,
        DiceRollMode $mode = DiceRollMode::Successes,
        ?string $comment = null,
    ): array {
        $character = null;
        if ($characterId !== null) {
            $character = Character::query()
                ->with('entity')
                ->findOrFail($characterId);

            if ((int) $character->chronicle_id !== (int) $chronicle->id) {
                throw new CharacterNotInChronicleException;
            }

            $isStoryteller = $user->isStorytellerIn($chronicle);
            if (! $isStoryteller && ! $character->isPlayableBy($user)) {
                throw new SceneDiceRollForbiddenException;
            }
        }

        $result = $this->dice->roll($pool, $difficulty, $diceType, $mode);
        $log = $this->sceneLogs->logDiceRoll($scene, $user, $character, $result, $isHidden, $comment);

        return [
            'result' => $result,
            'log' => $log,
        ];
    }
}
