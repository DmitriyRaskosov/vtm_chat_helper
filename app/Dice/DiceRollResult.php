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
        public bool $willpowerSpent = false,
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
            willpowerSpent: false,
        );
    }

    public function withWillpowerSpent(): self
    {
        if ($this->mode === DiceRollMode::Sum) {
            return $this;
        }

        $successes = $this->successes + 1;
        $net = $successes - $this->ones;

        if ($this->successes === 0 && $this->ones > 0) {
            $net = 1;
            $successes = 1;
        }

        $outcome = $net > 0 ? DiceRollOutcome::Success : DiceRollOutcome::Failure;

        return new self(
            pool: $this->pool,
            difficulty: $this->difficulty,
            diceType: $this->diceType,
            dice: $this->dice,
            successes: $successes,
            ones: $this->ones,
            net: $net,
            outcome: $outcome,
            mode: $this->mode,
            willpowerSpent: true,
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
            'willpower_spent' => $this->willpowerSpent,
        ];

        if ($this->mode === DiceRollMode::Sum) {
            $payload['sum'] = $this->net;
        }

        return $payload;
    }
}
