<?php

namespace App\Dice;

use App\Enums\DiceRollMode;
use App\Enums\DiceRollOutcome;

final readonly class DiceRollResult
{
    /**
     * @param  list<int>  $dice
     */
    public function __construct(
        public int $pool,
        public int $difficulty,
        public int $diceType,
        public array $dice,
        public int $successes,
        public int $ones,
        public int $net,
        public DiceRollOutcome $outcome,
        public DiceRollMode $mode = DiceRollMode::Successes,
    ) {}

    /**
     * @param  list<int>  $dice
     */
    public static function fromDice(
        int $pool,
        int $difficulty,
        int $diceType,
        array $dice,
        DiceRollMode $mode = DiceRollMode::Successes,
    ): self {
        if ($mode === DiceRollMode::Sum) {
            $sum = array_sum($dice);

            return new self(
                pool: $pool,
                difficulty: $difficulty,
                diceType: $diceType,
                dice: $dice,
                successes: 0,
                ones: 0,
                net: $sum,
                outcome: DiceRollOutcome::Success,
                mode: $mode,
            );
        }

        $successes = 0;
        $ones = 0;

        foreach ($dice as $value) {
            if ($value >= $difficulty) {
                $successes++;
            }
            if ($value === 1) {
                $ones++;
            }
        }

        $net = $successes - $ones;

        if ($successes === 0 && $ones > 0) {
            $outcome = DiceRollOutcome::Botch;
        } elseif ($net <= 0) {
            $outcome = DiceRollOutcome::Failure;
        } else {
            $outcome = DiceRollOutcome::Success;
        }

        return new self(
            pool: $pool,
            difficulty: $difficulty,
            diceType: $diceType,
            dice: $dice,
            successes: $successes,
            ones: $ones,
            net: $net,
            outcome: $outcome,
            mode: $mode,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        $payload = [
            'pool' => $this->pool,
            'difficulty' => $this->difficulty,
            'dice_type' => $this->diceType,
            'dice' => $this->dice,
            'successes' => $this->successes,
            'ones' => $this->ones,
            'net' => $this->net,
            'outcome' => $this->outcome->value,
            'mode' => $this->mode->value,
        ];

        if ($this->mode === DiceRollMode::Sum) {
            $payload['sum'] = $this->net;
        }

        return $payload;
    }
}
