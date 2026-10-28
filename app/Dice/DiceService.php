<?php

namespace App\Dice;

use App\Dice\Exceptions\InvalidDiceInputException;
use App\Enums\DiceRollMode;

final class DiceService
{
    private const MAX_POOL = 100;

    /** @var list<int> */
    private const ALLOWED_DICE_TYPES = [4, 6, 8, 10, 12, 20];

    public function roll(
        int $pool,
        int $difficulty = 6,
        int $diceType = 10,
        DiceRollMode $mode = DiceRollMode::Successes,
        bool $useWillpower = false,
    ): DiceRollResult {
        $this->assertValidRoll($pool, $difficulty, $diceType, $mode);

        $dice = [];
        for ($i = 0; $i < $pool; $i++) {
            $dice[] = random_int(1, $diceType);
        }

        $result = DiceRollResult::fromDice($pool, $difficulty, $diceType, $dice, $mode);

        if ($useWillpower) {
            return $result->withWillpowerSpent();
        }

        return $result;
    }

    private function assertValidRoll(int $pool, int $difficulty, int $diceType, DiceRollMode $mode): void
    {
        if ($pool < 1 || $pool > self::MAX_POOL) {
            throw new InvalidDiceInputException('Пул кубиков должен быть от 1 до '.self::MAX_POOL.'.');
        }

        if (! in_array($diceType, self::ALLOWED_DICE_TYPES, true)) {
            throw new InvalidDiceInputException('Недопустимый тип кубика.');
        }

        if ($mode === DiceRollMode::Sum) {
            return;
        }

        if ($difficulty < 1 || $difficulty > $diceType) {
            throw new InvalidDiceInputException('Сложность должна быть от 1 до '.$diceType.'.');
        }
    }
}
